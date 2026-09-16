<?php
/**
 * Nhập dữ liệu chấm công từ cơ sở dữ liệu của phần mềm cũ nằm chung máy chủ.
 *
 * Trang chấm công cũ dùng MySQL trên cùng host, nên đọc thẳng bảng của nó là
 * nhanh và đúng nhất — không phải xuất Excel rồi nhập lại mỗi tháng.
 *
 * Không đoán trước lược đồ của ai cả: quản trị chọn bảng, xem thử vài dòng, rồi
 * tự ghép cột sang các trường của nền tảng. Ba dạng bảng hay gặp đều nhận được:
 *   1. Mỗi dòng một ngày, có cột giờ vào và giờ ra.
 *   2. Mỗi dòng một ngày, có sẵn cột giờ công.
 *   3. Nhật ký chấm công thô: mỗi lần quẹt một dòng — gom theo người + ngày rồi
 *      lấy mốc sớm nhất làm giờ vào, muộn nhất làm giờ ra.
 *
 * @package khh-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Các schema của chính MySQL, không phải dữ liệu của ai. */
function khh_cc_he_thong() {
	return array( 'information_schema', 'performance_schema', 'mysql', 'sys' );
}

/** Danh sách cơ sở dữ liệu trên cùng máy chủ mà tài khoản WordPress đọc được. */
function khh_cc_databases() {
	global $wpdb;
	$hien = defined( 'DB_NAME' ) ? DB_NAME : '';
	$ds   = (array) $wpdb->get_col( 'SHOW DATABASES' ); // phpcs:ignore
	$out  = array();
	foreach ( $ds as $d ) {
		if ( in_array( strtolower( (string) $d ), khh_cc_he_thong(), true ) ) {
			continue;
		}
		$out[] = (string) $d;
	}
	if ( $hien && ! in_array( $hien, $out, true ) ) {
		array_unshift( $out, $hien );
	}
	return $out;
}

/** Bảng trong một cơ sở dữ liệu, kèm số dòng ước lượng. */
function khh_cc_tables( $db ) {
	global $wpdb;
	if ( ! khh_cc_db_ok( $db ) ) {
		return array();
	}
	$names = (array) $wpdb->get_col( 'SHOW TABLES FROM `' . esc_sql( $db ) . '`' ); // phpcs:ignore
	$out   = array();
	foreach ( $names as $t ) {
		if ( $db === DB_NAME && $t === khh_table() ) {
			continue;
		}
		$out[ (string) $t ] = (int) $wpdb->get_var( // phpcs:ignore
			'SELECT COUNT(*) FROM `' . esc_sql( $db ) . '`.`' . esc_sql( $t ) . '`'
		);
	}
	return $out;
}

/**
 * Chỉ nhận tên có thật trong danh sách máy chủ trả về.
 *
 * Tên bảng và tên cơ sở dữ liệu không đặt tham số được trong câu lệnh SQL, nên
 * cách an toàn duy nhất là đối chiếu với danh sách thật rồi mới ghép chuỗi.
 */
function khh_cc_db_ok( $db ) {
	global $wpdb;
	$db = (string) $db;
	if ( '' === $db || in_array( strtolower( $db ), khh_cc_he_thong(), true ) ) {
		return false;
	}
	return in_array( $db, (array) $wpdb->get_col( 'SHOW DATABASES' ), true ); // phpcs:ignore
}

function khh_cc_table_ok( $db, $table ) {
	global $wpdb;
	if ( ! khh_cc_db_ok( $db ) ) {
		return false;
	}
	return in_array(
		(string) $table,
		(array) $wpdb->get_col( 'SHOW TABLES FROM `' . esc_sql( $db ) . '`' ), // phpcs:ignore
		true
	);
}

function khh_cc_columns( $db, $table ) {
	global $wpdb;
	if ( ! khh_cc_table_ok( $db, $table ) ) {
		return array();
	}
	return (array) $wpdb->get_col( // phpcs:ignore
		'SHOW COLUMNS FROM `' . esc_sql( $db ) . '`.`' . esc_sql( $table ) . '`'
	);
}

/** Kiểu của từng cột, để biết cột ngày có lọc thẳng bằng SQL được không. */
function khh_cc_col_types( $db, $table ) {
	global $wpdb;
	if ( ! khh_cc_table_ok( $db, $table ) ) {
		return array();
	}
	$rows = $wpdb->get_results( // phpcs:ignore
		'SHOW COLUMNS FROM `' . esc_sql( $db ) . '`.`' . esc_sql( $table ) . '`',
		ARRAY_A
	);
	$out = array();
	foreach ( (array) $rows as $r ) {
		if ( isset( $r['Field'] ) ) {
			$out[ $r['Field'] ] = isset( $r['Type'] ) ? strtolower( (string) $r['Type'] ) : '';
		}
	}
	return $out;
}

/**
 * Đọc dòng từ bảng cũ.
 *
 * @param string $db    Cơ sở dữ liệu.
 * @param string $table Bảng.
 * @param int    $limit 0 là lấy hết.
 * @param array  $loc   col, from, to — lọc thẳng bằng SQL cho khỏi kéo cả bảng về.
 */
function khh_cc_rows( $db, $table, $limit = 0, $loc = array() ) {
	global $wpdb;
	$cols = khh_cc_columns( $db, $table );
	if ( ! $cols ) {
		return array();
	}

	$where = '';
	$args  = array();
	$col   = isset( $loc['col'] ) ? (string) $loc['col'] : '';
	if ( '' !== $col && in_array( $col, $cols, true ) ) {
		$types = khh_cc_col_types( $db, $table );
		$kieu  = isset( $types[ $col ] ) ? $types[ $col ] : '';
		/* Chỉ lọc bằng SQL khi cột thật sự là kiểu ngày. Cột ngày lưu dạng chữ
		   "15/09/2026" mà đem so sánh thì ra kết quả sai, thà đọc hết còn hơn. */
		if ( preg_match( '/^(date|datetime|timestamp)/', $kieu ) ) {
			$c = '`' . esc_sql( $col ) . '`';
			if ( ! empty( $loc['from'] ) ) {
				$where .= ( $where ? ' AND ' : ' WHERE ' ) . $c . ' >= %s';
				$args[] = $loc['from'] . ' 00:00:00';
			}
			if ( ! empty( $loc['to'] ) ) {
				$where .= ( $where ? ' AND ' : ' WHERE ' ) . $c . ' <= %s';
				$args[] = $loc['to'] . ' 23:59:59';
			}
		}
	}

	$sql = 'SELECT * FROM `' . esc_sql( $db ) . '`.`' . esc_sql( $table ) . '`' . $where
		. ( $limit ? ' LIMIT ' . (int) $limit : '' );
	if ( $args ) {
		$sql = $wpdb->prepare( $sql, $args ); // phpcs:ignore
	}
	$data = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( (array) $data as $r ) {
		$line = array();
		foreach ( $cols as $c2 ) {
			$line[] = isset( $r[ $c2 ] ) ? (string) $r[ $c2 ] : '';
		}
		$out[] = $line;
	}
	return $out;
}

/**
 * Chấm điểm xem bảng này có giống bảng chấm công không, để đưa lên đầu danh sách.
 *
 * Người dùng không phải là dân kỹ thuật, bắt họ dò giữa vài chục bảng là hỏng.
 * Chỉ xem cột của những bảng có tên nghi ngờ, cho khỏi nặng trang.
 */
function khh_cc_diem( $db, $table ) {
	$ten   = strtolower( (string) $table );
	$diem  = 0;
	if ( preg_match( '/(attend|checkinout|checkin|check_in|att_?log|punch|timesheet|cham_?cong|chamcong|bang_?cong)/', $ten ) ) {
		$diem += 3;
	}
	if ( $diem < 3 ) {
		return $diem;
	}
	$types = khh_cc_col_types( $db, $table );
	$co    = array( 'ngay' => false, 'nguoi' => false, 'gio' => false );
	foreach ( $types as $c => $kieu ) {
		$f = khh_cc_guess( $c );
		if ( 'date' === $f || 'time' === $f || preg_match( '/^(date|datetime|timestamp)/', $kieu ) ) {
			$co['ngay'] = true;
		}
		if ( 'code' === $f || 'name' === $f ) {
			$co['nguoi'] = true;
		}
		if ( in_array( $f, array( 'in', 'out', 'gio', 'time' ), true ) ) {
			$co['gio'] = true;
		}
	}
	return $diem + ( $co['ngay'] ? 2 : 0 ) + ( $co['nguoi'] ? 2 : 0 ) + ( $co['gio'] ? 1 : 0 );
}

/** Các trường ghép được, kèm mô tả hiện trên trang. */
function khh_cc_fields() {
	return array(
		'code' => array( 'Mã nhân sự', 'Khớp với Mã nhân sự trong hồ sơ. Chính xác nhất.' ),
		'name' => array( 'Họ và tên', 'Dùng khi bảng cũ không có mã. Trùng tên thì bỏ qua dòng đó.' ),
		'date' => array( 'Ngày', 'Bắt buộc. Nhận 2026-09-15, 15/09/2026 hoặc cả ngày giờ.' ),
		'in'   => array( 'Giờ vào', 'Để trống nếu bảng chỉ có giờ công.' ),
		'out'  => array( 'Giờ ra', '' ),
		'time' => array( 'Mốc chấm công (nhật ký thô)', 'Mỗi lần quẹt một dòng: sớm nhất thành giờ vào, muộn nhất thành giờ ra.' ),
		'gio'  => array( 'Giờ công', 'Nhận 8, 7.5, 7:30 hay 450 phút. Có cột này thì nó là số chốt.' ),
		'ca'   => array( 'Mã ca', 'VD C1-C2.' ),
		'note' => array( 'Ghi chú', '' ),
	);
}

/** Đoán trường từ tên cột kỹ thuật. */
function khh_cc_guess( $col ) {
	/* Tên cột hay viết dính kiểu EmployeeCode — tách ra trước rồi mới so. */
	$c   = preg_replace( '/([a-z0-9])([A-Z])/', '$1_$2', (string) $col );
	$c   = strtolower( preg_replace( '/[^a-z0-9]+/i', '_', $c ) );
	$c   = trim( $c, '_' );
	$gon = str_replace( '_', '', $c );
	$map = array(
		'code' => array( 'employee_id', 'employee_code', 'emp_code', 'emp_id', 'staff_id', 'staff_code', 'user_id', 'ma_nv', 'manv', 'ma_nhan_vien', 'ma_nhan_su', 'badge', 'badgenumber', 'pin' ),
		'name' => array( 'name', 'full_name', 'fullname', 'employee_name', 'staff_name', 'ho_ten', 'hoten', 'ten_nhan_vien', 'nhan_vien' ),
		'date' => array( 'date', 'work_date', 'att_date', 'ngay', 'ngay_cong', 'ngay_lam_viec', 'workday', 'day' ),
		'in'   => array( 'check_in', 'checkin', 'time_in', 'in_time', 'gio_vao', 'giovao', 'first_in', 'clock_in' ),
		'out'  => array( 'check_out', 'checkout', 'time_out', 'out_time', 'gio_ra', 'giora', 'last_out', 'clock_out' ),
		'time' => array( 'checktime', 'check_time', 'punch_time', 'log_time', 'att_time', 'time', 'timestamp', 'thoi_gian', 'gio_quet' ),
		'gio'  => array( 'work_hours', 'working_hours', 'hours', 'total_hours', 'gio_cong', 'giocong', 'so_gio', 'cong', 'duration', 'worked_minutes', 'total_minutes' ),
		'ca'   => array( 'shift', 'shift_code', 'shift_name', 'ca', 'ma_ca', 'ca_lam_viec' ),
		'note' => array( 'note', 'notes', 'remark', 'remarks', 'ghi_chu', 'description' ),
	);
	foreach ( $map as $field => $aliases ) {
		if ( in_array( $c, $aliases, true ) ) {
			return $field;
		}
	}
	/* Vòng hai: bỏ hết gạch dưới hai bên rồi so, cho "employeecode" khớp
	   "employee_code". Cố tình để sau vòng một, tránh đoán nhầm. */
	foreach ( $map as $field => $aliases ) {
		foreach ( $aliases as $a ) {
			if ( $gon === str_replace( '_', '', $a ) ) {
				return $field;
			}
		}
	}
	return '';
}

/** "2026-09-15 08:10:23" hay "8:10 AM" → "08:10". Không đọc được thì chuỗi rỗng. */
function khh_cc_gio( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return '';
	}
	if ( preg_match( '/(\d{1,2}):(\d{2})(?::\d{2})?\s*([ap])\.?m\.?/i', $v, $m ) ) {
		$h = (int) $m[1] % 12;
		if ( 'p' === strtolower( $m[3] ) ) {
			$h += 12;
		}
		return sprintf( '%02d:%02d', $h, (int) $m[2] );
	}
	if ( preg_match( '/(\d{1,2}):(\d{2})/', $v, $m ) ) {
		$h = (int) $m[1];
		$p = (int) $m[2];
		return ( $h >= 0 && $h < 24 && $p < 60 ) ? sprintf( '%02d:%02d', $h, $p ) : '';
	}
	return '';
}

/**
 * Giờ công của phần mềm cũ → số phút.
 *
 * Số lớn hơn 24 hiểu là đã tính bằng phút; phần mềm chấm công hay lưu kiểu đó.
 */
function khh_cc_phut( $v ) {
	$v = trim( str_replace( ',', '.', (string) $v ) );
	if ( '' === $v ) {
		return 0;
	}
	if ( preg_match( '/^(\d+)\s*[:h]\s*(\d{1,2})?$/i', $v, $m ) ) {
		return (int) $m[1] * 60 + (int) ( isset( $m[2] ) ? $m[2] : 0 );
	}
	if ( ! is_numeric( $v ) ) {
		return 0;
	}
	$n = (float) $v;
	if ( $n <= 0 ) {
		return 0;
	}
	return $n > 24 ? (int) round( $n ) : (int) round( $n * 60 );
}

/** Bảng tra người: mã nhân sự và họ tên → id hồ sơ. Tên trùng nhau thì loại bỏ. */
function khh_cc_index_staff() {
	$byCode = array();
	$byName = array();
	$dupName = array();
	foreach ( khh_get_coll( 'staff' ) as $id => $s ) {
		$code = isset( $s['code'] ) ? strtolower( trim( (string) $s['code'] ) ) : '';
		if ( '' !== $code ) {
			$byCode[ $code ] = $id;
		}
		$name = isset( $s['name'] ) ? khh_slug_vi( $s['name'] ) : '';
		if ( '' !== $name ) {
			if ( isset( $byName[ $name ] ) ) {
				$dupName[ $name ] = true;
			} else {
				$byName[ $name ] = $id;
			}
		}
	}
	foreach ( array_keys( $dupName ) as $n ) {
		unset( $byName[ $n ] );
	}
	return array( $byCode, $byName, $dupName );
}

/**
 * Đọc các dòng thô thành bản ghi chấm công theo người + kỳ.
 *
 * @param array $rows   Dòng dữ liệu.
 * @param array $header Tên cột.
 * @param array $map    Cột thứ i ghép sang trường nào.
 * @param array $opts   from, to: giới hạn khoảng ngày (YYYY-MM-DD), có thể rỗng.
 * @return array array( docs, stat )
 */
function khh_cc_build( $rows, $header, $map, $opts = array() ) {
	list( $byCode, $byName, $dupName ) = khh_cc_index_staff();

	$col = array();
	foreach ( (array) $map as $i => $f ) {
		if ( $f ) {
			$col[ $f ] = (int) $i;
		}
	}

	$from = isset( $opts['from'] ) ? khh_parse_date( $opts['from'] ) : '';
	$to   = isset( $opts['to'] ) ? khh_parse_date( $opts['to'] ) : '';

	$stat = array(
		'rows'    => 0,
		'used'    => 0,
		'noStaff' => 0,
		'noDate'  => 0,
		'outside' => 0,
		'dupName' => 0,
		'people'  => array(),
		'lost'    => array(),
	);

	$days = array(); // staffId => cycle => dd => giá trị đang gom.

	foreach ( (array) $rows as $r ) {
		++$stat['rows'];

		$get = function ( $f ) use ( $r, $col ) {
			return isset( $col[ $f ] ) && isset( $r[ $col[ $f ] ] ) ? trim( (string) $r[ $col[ $f ] ] ) : '';
		};

		/* Ai */
		$sid  = '';
		$code = strtolower( $get( 'code' ) );
		if ( '' !== $code && isset( $byCode[ $code ] ) ) {
			$sid = $byCode[ $code ];
		}
		if ( ! $sid ) {
			$nm = khh_slug_vi( $get( 'name' ) );
			if ( '' !== $nm ) {
				if ( isset( $byName[ $nm ] ) ) {
					$sid = $byName[ $nm ];
				} elseif ( isset( $dupName[ $nm ] ) ) {
					++$stat['dupName'];
				}
			}
		}
		if ( ! $sid ) {
			++$stat['noStaff'];
			$ai = $get( 'code' ) ? $get( 'code' ) : $get( 'name' );
			if ( '' !== $ai && count( $stat['lost'] ) < 20 && ! in_array( $ai, $stat['lost'], true ) ) {
				$stat['lost'][] = $ai;
			}
			continue;
		}

		/* Ngày — lấy từ cột ngày, không có thì lấy từ chính mốc chấm công. */
		$ngay = khh_parse_date( $get( 'date' ) );
		if ( '' === $ngay ) {
			$ngay = khh_parse_date( $get( 'time' ) );
		}
		if ( '' === $ngay ) {
			++$stat['noDate'];
			continue;
		}
		if ( ( $from && $ngay < $from ) || ( $to && $ngay > $to ) ) {
			++$stat['outside'];
			continue;
		}

		$cyc = substr( $ngay, 0, 7 );
		$dd  = substr( $ngay, 8, 2 );
		if ( ! isset( $days[ $sid ] ) ) {
			$days[ $sid ] = array();
		}
		if ( ! isset( $days[ $sid ][ $cyc ] ) ) {
			$days[ $sid ][ $cyc ] = array();
		}
		if ( ! isset( $days[ $sid ][ $cyc ][ $dd ] ) ) {
			$days[ $sid ][ $cyc ][ $dd ] = array();
		}
		$o = &$days[ $sid ][ $cyc ][ $dd ];

		$vao = khh_cc_gio( $get( 'in' ) );
		$ra  = khh_cc_gio( $get( 'out' ) );
		$moc = khh_cc_gio( $get( 'time' ) );
		if ( '' !== $moc ) {
			/* Nhật ký thô: sớm nhất là vào, muộn nhất là ra. */
			if ( ! isset( $o['in'] ) || $moc < $o['in'] ) {
				$o['in'] = $moc;
			}
			if ( ! isset( $o['out'] ) || $moc > $o['out'] ) {
				$o['out'] = $moc;
			}
		}
		if ( '' !== $vao && ( ! isset( $o['in'] ) || $vao < $o['in'] ) ) {
			$o['in'] = $vao;
		}
		if ( '' !== $ra && ( ! isset( $o['out'] ) || $ra > $o['out'] ) ) {
			$o['out'] = $ra;
		}

		$phut = khh_cc_phut( $get( 'gio' ) );
		if ( $phut > 0 ) {
			$o['m'] = isset( $o['m'] ) ? $o['m'] + $phut : $phut;
		}
		$ca = $get( 'ca' );
		if ( '' !== $ca && empty( $o['ca'] ) ) {
			$o['ca'] = sanitize_text_field( $ca );
		}
		$note = $get( 'note' );
		if ( '' !== $note && empty( $o['note'] ) ) {
			$o['note'] = sanitize_text_field( $note );
		}
		unset( $o );

		++$stat['used'];
		$stat['people'][ $sid ] = true;
	}

	/* Giờ vào bằng giờ ra nghĩa là cả ngày chỉ quẹt một lần — giữ giờ vào thôi. */
	$docs = array();
	foreach ( $days as $sid => $cycs ) {
		foreach ( $cycs as $cyc => $dd ) {
			foreach ( $dd as $k => $v ) {
				if ( isset( $v['in'], $v['out'] ) && $v['in'] === $v['out'] ) {
					unset( $dd[ $k ]['out'] );
				}
			}
			$docs[] = array(
				'id'      => $sid . '_' . str_replace( '-', '', $cyc ),
				'staffId' => $sid,
				'cycle'   => $cyc,
				'days'    => $dd,
			);
		}
	}
	$stat['people'] = count( $stat['people'] );
	$stat['docs']   = count( $docs );
	return array( $docs, $stat );
}

/* ------------------------------------------------------------------ *
 * Đọc thẳng — không chép dữ liệu sang
 *
 * Dữ liệu chấm công đã nằm sẵn trong cơ sở dữ liệu của phần mềm cũ. Chép nó
 * sang bảng của nền tảng là tạo ra hai bản: sửa bên kia thì bên này sai, và
 * phải nhớ chạy lại mỗi tháng. Nên nền tảng ĐỌC THẲNG bảng đó mỗi lần trả dữ
 * liệu về giao diện, không ghi bản sao nào.
 *
 * Việc duy nhất phải khai một lần là bảng nào và cột nào — máy không tự đoán
 * được lược đồ của phần mềm khác.
 * ------------------------------------------------------------------ */

const KHH_CC_NGUON  = 'khh_cc_nguon';
const KHH_CC_NHIP   = 120;                 // Giây — bộ nhớ đệm, cho khỏi hỏi lại liên tục.
const KHH_CC_CACHE  = 'khh_cc_doc';
const KHH_CC_VER    = 'khh_cc_ver';

function khh_cc_get_nguon() {
	$d = array(
		'bat'   => 0,
		'db'    => '',
		'tbl'   => '',
		'map'   => array(),
		'thang' => 3,   // Hiện công của bao nhiêu tháng gần nhất.
		'ket'   => array(),
	);
	$o = get_option( KHH_CC_NGUON, array() );
	return array_merge( $d, is_array( $o ) ? $o : array() );
}

function khh_cc_set_nguon( $v ) {
	update_option( KHH_CC_NGUON, $v );
	delete_transient( KHH_CC_CACHE );
}

/** Cột nào đang được ghép sang trường ngày — dùng để lọc thẳng bằng SQL. */
function khh_cc_cot_ngay( $n ) {
	$cols = khh_cc_columns( $n['db'], $n['tbl'] );
	$uu   = '';
	$phu  = '';
	foreach ( (array) $n['map'] as $i => $f ) {
		if ( ! isset( $cols[ $i ] ) ) {
			continue;
		}
		if ( 'date' === $f && '' === $uu ) {
			$uu = $cols[ $i ];
		}
		if ( 'time' === $f && '' === $phu ) {
			$phu = $cols[ $i ];
		}
	}
	return $uu ? $uu : $phu;
}

/** Khoảng ngày đang hiện: N tháng gần nhất tính cả tháng này. */
function khh_cc_khoang( $n ) {
	$thang = max( 1, min( 12, (int) $n['thang'] ) );
	$moc   = current_time( 'timestamp' ); // phpcs:ignore
	return array(
		gmdate( 'Y-m-01', strtotime( '-' . ( $thang - 1 ) . ' month', $moc ) ),
		gmdate( 'Y-m-t', $moc ),
	);
}

/**
 * Đọc bảng cũ rồi dựng thành bản ghi chấm công — KHÔNG ghi xuống đâu cả.
 *
 * @param array $n Nguồn; để trống thì lấy nguồn đã khai.
 * @return array|null array( docs, stat ), hoặc null nếu chưa khai / không đọc được.
 */
function khh_cc_doc_song( $n = null ) {
	$n = null === $n ? khh_cc_get_nguon() : $n;
	if ( empty( $n['db'] ) || empty( $n['tbl'] ) || ! khh_cc_table_ok( $n['db'], $n['tbl'] ) ) {
		return null;
	}
	list( $tu, $den ) = khh_cc_khoang( $n );
	$header = khh_cc_columns( $n['db'], $n['tbl'] );
	$rows   = khh_cc_rows(
		$n['db'],
		$n['tbl'],
		0,
		array(
			'col'  => khh_cc_cot_ngay( $n ),
			'from' => $tu,
			'to'   => $den,
		)
	);
	list( $docs, $stat ) = khh_cc_build(
		$rows,
		$header,
		$n['map'],
		array(
			'from' => $tu,
			'to'   => $den,
		)
	);
	$stat['tu']  = $tu;
	$stat['den'] = $den;
	return array( $docs, $stat );
}

/**
 * Bản ghi chấm công để trả về giao diện: số của phần mềm cũ, đè lên bởi những
 * gì người dùng đã tự sửa trong nền tảng.
 *
 * Sửa tay luôn thắng — nếu không thì lần đọc sau số cũ lại quay về.
 *
 * @return array|null array( docs, ver, stat ) — ver là mốc để giao diện biết có gì mới.
 */
/** Đang lấy chấm công từ đâu: 'vhcc' (tự nối), 'bang' (bảng đã khai), hay '' (chưa có). */
function khh_cc_nguon_dang_dung() {
	$n = khh_cc_get_nguon();
	if ( ! empty( $n['bat'] ) && ! empty( $n['tbl'] ) ) {
		return 'bang';
	}
	return function_exists( 'khh_vhcc_co' ) && khh_vhcc_co() ? 'vhcc' : '';
}

function khh_cc_song() {
	$kieu = khh_cc_nguon_dang_dung();
	if ( '' === $kieu ) {
		return null;
	}
	$c = get_transient( KHH_CC_CACHE );
	if ( is_array( $c ) ) {
		return $c;
	}

	$n = khh_cc_get_nguon();
	/* Plugin Chấm Công (K&H) cùng site: nối thẳng, không cần ai khai bảng. Khai tay
	   một bảng khác thì bảng đó được ưu tiên. */
	$kq = 'vhcc' === $kieu ? khh_vhcc_doc_song( (int) $n['thang'] ) : khh_cc_doc_song( $n );
	if ( null === $kq ) {
		return null;
	}
	list( $docs, $stat ) = $kq;

	/* Đè bằng bản ghi của nền tảng: ngày nào người dùng đã sửa tay thì giữ. */
	$tay = khh_get_coll( 'attendance' );
	$out = array();
	foreach ( $docs as $d ) {
		$cu = isset( $tay[ $d['id'] ] ) ? $tay[ $d['id'] ] : null;
		if ( $cu ) {
			$ngay = isset( $cu['days'] ) && is_array( $cu['days'] ) ? $cu['days'] : array();
			foreach ( $ngay as $k => $v ) {
				$d['days'][ $k ] = isset( $d['days'][ $k ] ) ? array_merge( $d['days'][ $k ], $v ) : $v;
			}
			$d = array_merge( $cu, $d );
		}
		$out[] = $d;
	}

	/* Mốc chỉ nhích khi nội dung thật sự đổi, để giao diện hỏi mỗi 8 giây mà
	   không phải tải lại cả đống dữ liệu không đổi. */
	$hash = md5( (string) wp_json_encode( $out ) );
	$v    = get_option( KHH_CC_VER, array() );
	$v    = is_array( $v ) ? $v : array();
	if ( ! isset( $v['hash'] ) || $v['hash'] !== $hash ) {
		/* Mốc phải LUÔN lớn hơn mốc cũ và lớn hơn mốc thời gian của chính lượt
		   trả về này. Hai lần đổi trong cùng một mili giây mà mốc bằng nhau thì
		   giao diện tưởng chưa có gì mới và bỏ qua thay đổi. */
		$cu = isset( $v['ver'] ) ? (int) $v['ver'] : 0;
		$v  = array(
			'hash' => $hash,
			'ver'  => max( (int) round( microtime( true ) * 1000 ) + 1, $cu + 1 ),
		);
		update_option( KHH_CC_VER, $v );
	}

	$kq = array(
		'docs' => $out,
		'ver'  => (int) $v['ver'],
		'stat' => $stat,
		'at'   => time(),
	);
	set_transient( KHH_CC_CACHE, $kq, KHH_CC_NHIP );
	return $kq;
}

/** Người dùng sửa tay ngày công thì bỏ bộ nhớ đệm, để lần đọc sau thấy ngay. */
function khh_cc_quen( $coll = 'attendance' ) {
	if ( 'attendance' === $coll ) {
		delete_transient( KHH_CC_CACHE );
	}
}

/* ------------------------------------------------------------------ *
 * Trang quản trị: ba bước, không cần JavaScript
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'khh_cc_menu', 24 );
function khh_cc_menu() {
	add_submenu_page(
		'khh-platform',
		'Nguồn chấm công',
		'Nguồn chấm công',
		'manage_options',
		'khh-nhap-cham-cong',
		'khh_cc_page'
	);
}

function khh_cc_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Không đủ quyền.' );
	}

	$buoc   = 1;
	$db     = '';
	$tbl    = '';
	$loi    = '';
	$xong   = false;
	$map    = array();
	$thang  = 3;
	$header = array();
	$xem    = array();
	$stat   = null;

	/* Nút của khối trạng thái đầu trang. */
	if ( isset( $_POST['khh_cc_viec'] ) && check_admin_referer( 'khh_cc' ) ) {
		$viec = sanitize_key( wp_unslash( $_POST['khh_cc_viec'] ) );
		$n    = khh_cc_get_nguon();
		if ( 'doclai' === $viec ) {
			delete_transient( KHH_CC_CACHE );
		} elseif ( 'tat' === $viec || 'bat' === $viec ) {
			$n['bat'] = 'bat' === $viec ? 1 : 0;
			khh_cc_set_nguon( $n );
		}
	}

	if ( isset( $_POST['khh_cc_buoc'] ) && check_admin_referer( 'khh_cc' ) ) {
		$buoc = (int) $_POST['khh_cc_buoc'];
		$db   = isset( $_POST['db'] ) ? sanitize_text_field( wp_unslash( $_POST['db'] ) ) : '';
		$tbl  = isset( $_POST['tbl'] ) ? sanitize_text_field( wp_unslash( $_POST['tbl'] ) ) : '';

		/* Bước 1 gửi một ô duy nhất "csdl|bảng" để khỏi cần JavaScript. */
		if ( ! $tbl && isset( $_POST['nguon'] ) ) {
			$p   = explode( '|', sanitize_text_field( wp_unslash( $_POST['nguon'] ) ), 2 );
			$db  = $p[0];
			$tbl = isset( $p[1] ) ? $p[1] : '';
		}

		if ( ! khh_cc_table_ok( $db, $tbl ) ) {
			$loi  = 'Không tìm thấy bảng đó. Chọn lại từ danh sách.';
			$buoc = 1;
		} else {
			$header = khh_cc_columns( $db, $tbl );
			if ( isset( $_POST['map'] ) && is_array( $_POST['map'] ) ) {
				foreach ( wp_unslash( $_POST['map'] ) as $i => $f ) { // phpcs:ignore
					$map[ (int) $i ] = sanitize_key( $f );
				}
			} else {
				foreach ( $header as $i => $c ) {
					$map[ $i ] = khh_cc_guess( $c );
				}
			}
			if ( isset( $_POST['thang'] ) ) {
				$thang = max( 1, min( 12, (int) $_POST['thang'] ) );
			}

			if ( 2 === $buoc ) {
				$xem = khh_cc_rows( $db, $tbl, 8 );
			} elseif ( $buoc >= 3 ) {
				$co_ngay = false;
				foreach ( $map as $f ) {
					if ( 'date' === $f || 'time' === $f ) {
						$co_ngay = true;
					}
				}
				if ( ! $co_ngay ) {
					$loi  = 'Chưa ghép cột Ngày (hoặc Mốc chấm công). Không có ngày thì không biết công thuộc hôm nào.';
					$buoc = 2;
					$xem  = khh_cc_rows( $db, $tbl, 8 );
				} else {
					$thu = array(
						'bat'   => 1,
						'db'    => $db,
						'tbl'   => $tbl,
						'map'   => $map,
						'thang' => $thang,
					);
					$kq  = khh_cc_doc_song( $thu );
					if ( null === $kq ) {
						$loi  = 'Không đọc được bảng này.';
						$buoc = 2;
					} else {
						$stat = $kq[1];
						if ( 4 === $buoc ) {
							$n = khh_cc_get_nguon();
							khh_cc_set_nguon( array_merge( $n, $thu, array( 'ket' => $stat ) ) );
							$xong = true;
						}
					}
				}
			}
		}
	}

	$fields = khh_cc_fields();
	$n      = khh_cc_get_nguon();
	?>
	<div class="wrap">
		<h1>Nguồn chấm công</h1>
		<p class="description" style="max-width:860px">
			Nền tảng <strong>đọc thẳng</strong> bảng chấm công của phần mềm cũ nằm chung máy chủ —
			không chép sang, không có bản thứ hai để lệch nhau. Sửa bên phần mềm cũ thì bên này
			đổi theo. Trang này chỉ đọc, không ghi và không xoá gì bên cơ sở dữ liệu đó.
		</p>
		<?php if ( $loi ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $loi ); ?></p></div>
		<?php endif; ?>

		<?php if ( $xong ) : ?>
			<div class="notice notice-success"><p>
				<strong>Xong.</strong> Từ giờ dữ liệu chấm công hiện thẳng trong nền tảng, không phải làm gì thêm.
				Mở <a href="<?php echo esc_url( home_url( '/?khh_app=1' ) ); ?>" target="_blank" rel="noopener">Dữ liệu chấm công</a>
				hoặc <strong>Bảng công cơ sở</strong> để xem.
			</p></div>
		<?php endif; ?>

		<?php
		if ( ! ( $n['db'] && $n['tbl'] ) && 'vhcc' === khh_cc_nguon_dang_dung() ) :
			$song = khh_cc_song();
			$k    = $song ? $song['stat'] : array();
			?>
			<div class="notice notice-success" style="padding:12px 14px">
				<p style="margin:0 0 6px">
					<strong>Đã tự nối với plugin Chấm Công (K&amp;H)</strong> đang chạy trên site này —
					không cần khai gì. Hiện công của <?php echo (int) $n['thang']; ?> tháng gần nhất,
					cơ sở của từng người lấy theo sổ nhân viên bên ấy.
				</p>
				<?php if ( $k && isset( $k['rows'] ) ) : ?>
					<p style="margin:0 0 8px" class="description">
						Đang đọc <strong><?php echo (int) $k['rows']; ?></strong> dòng
						(<?php echo esc_html( $k['tu'] . ' → ' . $k['den'] ); ?>)
						· dùng được <strong><?php echo (int) $k['used']; ?></strong>
						· <strong><?php echo (int) $k['people']; ?></strong> người
						<?php if ( ! empty( $k['noStaff'] ) ) : ?>
							· <span style="color:#b32d2e"><?php echo (int) $k['noStaff']; ?> dòng của mã chưa có hồ sơ bên nền tảng</span>
						<?php endif; ?>
					</p>
					<?php if ( ! empty( $k['lost'] ) ) : ?>
						<p style="margin:0 0 8px" class="description">
							Mã chưa có hồ sơ (vài ví dụ): <code><?php echo esc_html( implode( ', ', array_slice( $k['lost'], 0, 8 ) ) ); ?></code>
							— thêm người đó vào Hồ sơ nhân sự với đúng mã là khớp.
						</p>
					<?php endif; ?>
				<?php endif; ?>
				<form method="post" style="display:inline">
					<?php wp_nonce_field( 'khh_cc' ); ?>
					<input type="hidden" name="khh_cc_viec" value="doclai">
					<button class="button">Đọc lại ngay</button>
				</form>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=khh-nhap-cham-cong&doi=1' ) ); ?>">Dùng bảng khác thay vì plugin này</a>
			</div>
		<?php endif; ?>

		<?php
		if ( $n['db'] && $n['tbl'] ) :
			$song = khh_cc_song();
			$k    = $song ? $song['stat'] : ( is_array( $n['ket'] ) ? $n['ket'] : array() );
			?>
			<div class="notice <?php echo $n['bat'] ? 'notice-success' : 'notice-warning'; ?>" style="padding:12px 14px">
				<p style="margin:0 0 6px">
					<strong><?php echo $n['bat'] ? 'Đang đọc thẳng' : 'Đã tắt'; ?></strong>
					bảng <code><?php echo esc_html( $n['db'] . '.' . $n['tbl'] ); ?></code>
					— hiện công của <?php echo (int) $n['thang']; ?> tháng gần nhất.
				</p>
				<?php if ( $k && isset( $k['rows'] ) ) : ?>
					<p style="margin:0 0 8px" class="description">
						Đang đọc <strong><?php echo (int) $k['rows']; ?></strong> dòng
						<?php if ( isset( $k['tu'] ) ) : ?>
							(<?php echo esc_html( $k['tu'] . ' → ' . $k['den'] ); ?>)
						<?php endif; ?>
						· dùng được <strong><?php echo (int) $k['used']; ?></strong>
						· <strong><?php echo (int) $k['people']; ?></strong> người
						<?php if ( ! empty( $k['noStaff'] ) ) : ?>
							· <span style="color:#b32d2e"><?php echo (int) $k['noStaff']; ?> dòng chưa khớp người</span>
						<?php endif; ?>
					</p>
					<?php if ( ! empty( $k['lost'] ) ) : ?>
						<p style="margin:0 0 8px" class="description">
							Chưa khớp (vài ví dụ): <code><?php echo esc_html( implode( ', ', array_slice( $k['lost'], 0, 8 ) ) ); ?></code>
							— sửa Mã nhân sự trong hồ sơ cho khớp là hết.
						</p>
					<?php endif; ?>
				<?php endif; ?>
				<form method="post" style="display:inline">
					<?php wp_nonce_field( 'khh_cc' ); ?>
					<input type="hidden" name="khh_cc_viec" value="doclai">
					<button class="button">Đọc lại ngay</button>
				</form>
				<form method="post" style="display:inline">
					<?php wp_nonce_field( 'khh_cc' ); ?>
					<input type="hidden" name="khh_cc_viec" value="<?php echo $n['bat'] ? 'tat' : 'bat'; ?>">
					<button class="button"><?php echo $n['bat'] ? 'Tắt' : 'Bật lại'; ?></button>
				</form>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=khh-nhap-cham-cong&doi=1' ) ); ?>">Đổi bảng</a>
			</div>
		<?php endif; ?>

		<?php
		$an_buoc1 = 1 === $buoc && ! isset( $_GET['doi'] ) && '' !== khh_cc_nguon_dang_dung(); // phpcs:ignore
		if ( 1 === $buoc && ! $an_buoc1 ) :
			?>
			<h2>Bước 1 — Chọn bảng</h2>
			<form method="post">
				<?php wp_nonce_field( 'khh_cc' ); ?>
				<input type="hidden" name="khh_cc_buoc" value="2">
				<p>
					<?php
					/* Đoán sẵn bảng nào giống bảng chấm công rồi đẩy lên đầu. */
					$ung_vien = array();
					$moi_bang = array();
					foreach ( khh_cc_databases() as $d ) {
						$ts = khh_cc_tables( $d );
						if ( ! $ts ) {
							continue;
						}
						$moi_bang[ $d ] = $ts;
						foreach ( $ts as $t => $sl ) {
							$diem = khh_cc_diem( $d, $t );
							if ( $diem >= 5 ) {
								$ung_vien[ $d . '|' . $t ] = $diem;
							}
						}
					}
					arsort( $ung_vien );
					$tot_nhat = $ung_vien ? key( $ung_vien ) : '';
					?>
					<select name="nguon" style="min-width:460px">
						<?php
						if ( $ung_vien ) {
							echo '<optgroup label="Có vẻ là bảng chấm công">';
							foreach ( array_keys( $ung_vien ) as $kk ) {
								$pp = explode( '|', $kk, 2 );
								echo '<option value="' . esc_attr( $kk ) . '"' . selected( $kk, $tot_nhat, false ) . '>'
									. esc_html( $pp[1] ) . ' (' . esc_html( $pp[0] ) . ') — '
									. esc_html( number_format_i18n( $moi_bang[ $pp[0] ][ $pp[1] ] ) ) . ' dòng</option>';
							}
							echo '</optgroup>';
						}
						foreach ( $moi_bang as $d => $ts ) {
							echo '<optgroup label="' . esc_attr( $d ) . ( DB_NAME === $d ? ' (cơ sở dữ liệu của website này)' : '' ) . '">';
							foreach ( $ts as $t => $sl ) {
								echo '<option value="' . esc_attr( $d . '|' . $t ) . '">'
									. esc_html( $t ) . ' — ' . esc_html( number_format_i18n( $sl ) ) . ' dòng</option>';
							}
							echo '</optgroup>';
						}
						?>
					</select>
				</p>
				<p class="description">
					<?php if ( $ung_vien ) : ?>
						Đã chọn sẵn bảng trông giống bảng chấm công nhất. Bấm xem thử, nhìn vài dòng đầu là biết đúng chưa.
					<?php else : ?>
						Bảng chấm công thường tên là <code>attendance</code>, <code>checkinout</code>,
						<code>att_log</code>, <code>cham_cong</code> hay <code>timesheet</code>.
					<?php endif; ?>
				</p>
				<p><button class="button button-primary">Xem thử bảng này</button></p>
			</form>

		<?php elseif ( 2 === $buoc ) : ?>
			<h2>Bước 2 — Ghép cột</h2>
			<p class="description">
				Bảng <code><?php echo esc_html( $db . '.' . $tbl ); ?></code>.
				Cột nào không dùng thì để <em>— bỏ qua —</em>.
			</p>
			<form method="post">
				<?php wp_nonce_field( 'khh_cc' ); ?>
				<input type="hidden" name="khh_cc_buoc" value="3">
				<input type="hidden" name="db" value="<?php echo esc_attr( $db ); ?>">
				<input type="hidden" name="tbl" value="<?php echo esc_attr( $tbl ); ?>">
				<table class="widefat striped" style="max-width:100%">
					<thead><tr><th style="width:230px">Cột trong bảng cũ</th><th style="width:280px">Đưa vào trường</th><th>Vài dòng đầu</th></tr></thead>
					<tbody>
					<?php foreach ( $header as $i => $c ) : ?>
						<tr>
							<td><code><?php echo esc_html( $c ); ?></code></td>
							<td>
								<select name="map[<?php echo (int) $i; ?>]">
									<option value="">— bỏ qua —</option>
									<?php foreach ( $fields as $kk => $f ) : ?>
										<option value="<?php echo esc_attr( $kk ); ?>"
											<?php selected( isset( $map[ $i ] ) ? $map[ $i ] : '', $kk ); ?>>
											<?php echo esc_html( $f[0] ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td class="description">
								<?php
								$mau = array();
								foreach ( $xem as $r ) {
									if ( isset( $r[ $i ] ) && '' !== $r[ $i ] && count( $mau ) < 3 ) {
										$mau[] = $r[ $i ];
									}
								}
								echo esc_html( implode( ' · ', $mau ) );
								?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="thang">Hiện công của</label></th>
						<td>
							<input type="number" id="thang" name="thang" value="<?php echo (int) $thang; ?>" min="1" max="12" style="width:70px">
							tháng gần nhất
							<p class="description">Mỗi lần đọc chỉ lấy khoảng này, để bảng vài trăm nghìn dòng vẫn nhẹ.</p>
						</td>
					</tr>
				</table>
				<p>
					<button class="button button-primary">Đối chiếu thử</button>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=khh-nhap-cham-cong&doi=1' ) ); ?>">Chọn bảng khác</a>
				</p>
				<h3>Các trường nhận được</h3>
				<table class="widefat striped" style="max-width:820px"><tbody>
				<?php foreach ( $fields as $f ) : ?>
					<tr><td style="width:220px"><strong><?php echo esc_html( $f[0] ); ?></strong></td>
						<td class="description"><?php echo esc_html( $f[1] ); ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			</form>

		<?php elseif ( 3 === $buoc && $stat ) : ?>
			<h2>Bước 3 — Đối chiếu</h2>
			<p class="description">Đọc thử <?php echo esc_html( $stat['tu'] . ' → ' . $stat['den'] ); ?>, chưa lưu gì.</p>
			<table class="widefat striped" style="max-width:720px"><tbody>
				<tr><td>Đọc được</td><td><strong><?php echo (int) $stat['rows']; ?></strong> dòng</td></tr>
				<tr><td>Dùng được</td><td><strong><?php echo (int) $stat['used']; ?></strong> dòng
					· <strong><?php echo (int) $stat['people']; ?></strong> người
					· <strong><?php echo (int) $stat['docs']; ?></strong> bản ghi theo tháng</td></tr>
				<tr><td>Không tìm thấy người trong hồ sơ</td>
					<td<?php echo $stat['noStaff'] ? ' style="color:#b32d2e"' : ''; ?>><?php echo (int) $stat['noStaff']; ?> dòng</td></tr>
				<tr><td>Không đọc được ngày</td>
					<td<?php echo $stat['noDate'] ? ' style="color:#b32d2e"' : ''; ?>><?php echo (int) $stat['noDate']; ?> dòng</td></tr>
				<tr><td>Bỏ vì trùng tên trong hồ sơ</td>
					<td<?php echo $stat['dupName'] ? ' style="color:#b32d2e"' : ''; ?>><?php echo (int) $stat['dupName']; ?> dòng</td></tr>
			</tbody></table>
			<?php if ( $stat['lost'] ) : ?>
				<p class="description" style="max-width:860px">Chưa khớp được (vài ví dụ):
					<code><?php echo esc_html( implode( ', ', $stat['lost'] ) ); ?></code>.
					Thường là do Mã nhân sự bên hồ sơ để trống hoặc ghi khác. Sửa ở
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=khh-import' ) ); ?>">Nhập / Xuất dữ liệu</a> —
					không cần quay lại trang này, dữ liệu tự khớp ngay sau đó.</p>
			<?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'khh_cc' ); ?>
				<input type="hidden" name="khh_cc_buoc" value="4">
				<input type="hidden" name="db" value="<?php echo esc_attr( $db ); ?>">
				<input type="hidden" name="tbl" value="<?php echo esc_attr( $tbl ); ?>">
				<input type="hidden" name="thang" value="<?php echo (int) $thang; ?>">
				<?php foreach ( $map as $i => $f ) : ?>
					<input type="hidden" name="map[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $f ); ?>">
				<?php endforeach; ?>
				<p>
					<button class="button button-primary"<?php disabled( 0, (int) $stat['used'] ); ?>>Dùng bảng này</button>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=khh-nhap-cham-cong&doi=1' ) ); ?>">Chọn bảng khác</a>
				</p>
				<p class="description">Bấm xong là hiện luôn trong nền tảng. Không chép dữ liệu, không phải chạy lại hàng tháng.</p>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
