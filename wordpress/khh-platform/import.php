<?php
/**
 * Nhập hồ sơ nhân sự từ hệ thống cũ (CSV / Excel lưu dạng CSV) và xuất ra CSV.
 *
 * Quy trình hai bước: dán hoặc tải file lên → soát bảng ghép cột → nhập.
 * Không ghi đè mù: người dùng chọn khớp theo Mã nhân sự hay Email, và chọn
 * có cập nhật người đã có hay bỏ qua.
 *
 * @package khh-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Các cột app hiểu, kèm những tên thường gặp ở phần mềm nhân sự tiếng Việt. */
function khh_import_fields() {
	return array(
		'name'       => array( 'Họ và tên', array( 'họ và tên', 'họ tên', 'hoten', 'ho ten', 'tên', 'ten', 'name', 'full name', 'nhân viên', 'tên nhân viên' ) ),
		// Cố tình KHÔNG nhận cột tên "id": ở hầu hết phần mềm đó là số thứ tự nội bộ,
		// lấy nhầm làm mã nhân sự sẽ khớp sai người khi nhập lần sau.
		'code'       => array( 'Mã nhân sự', array( 'mã', 'ma', 'mã nv', 'ma nv', 'manv', 'mã nhân viên', 'mã nhân sự', 'code', 'employee code', 'employee id', 'mã số nhân viên' ) ),
		'title'      => array( 'Chức danh', array( 'chức danh', 'chuc danh', 'chức vụ', 'vị trí', 'title', 'position', 'job title' ) ),
		'dept'       => array( 'Bộ phận', array( 'bộ phận', 'bo phan', 'phòng ban', 'phòng', 'đơn vị', 'department', 'dept', 'team' ) ),
		'unit'       => array( 'Mảng kinh doanh', array( 'mảng', 'mang', 'mảng kinh doanh', 'khối', 'khoi', 'công ty', 'cong ty', 'thương hiệu', 'brand', 'business unit', 'unit', 'company' ) ),
		'email'      => array( 'Email', array( 'email', 'e-mail', 'thư điện tử', 'mail' ) ),
		'phone'      => array( 'Điện thoại', array( 'điện thoại', 'dien thoai', 'sđt', 'sdt', 'số điện thoại', 'phone', 'mobile', 'di động' ) ),
		'dob'        => array( 'Ngày sinh', array( 'ngày sinh', 'ngay sinh', 'sinh ngày', 'dob', 'birthday', 'date of birth' ) ),
		'gender'     => array( 'Giới tính', array( 'giới tính', 'gioi tinh', 'gender', 'sex' ) ),
		'start'      => array( 'Ngày vào làm', array( 'ngày vào', 'ngày vào làm', 'ngày bắt đầu', 'ngay vao lam', 'ngày tuyển', 'start', 'start date', 'join date', 'ngày ký hđ' ) ),
		'official'   => array( 'Ngày chính thức', array( 'ngày chính thức', 'chính thức', 'official', 'ngày hết thử việc' ) ),
		'salary'     => array( 'Lương cơ bản', array( 'lương', 'luong', 'lương cơ bản', 'mức lương', 'salary', 'basic salary', 'lương hợp đồng' ) ),
		'allowance'  => array( 'Phụ cấp', array( 'phụ cấp', 'phu cap', 'trợ cấp', 'allowance' ) ),
		'taxCode'    => array( 'Mã số thuế', array( 'mã số thuế', 'mst', 'tax code', 'tax' ) ),
		'bhxh'       => array( 'Số sổ BHXH', array( 'bhxh', 'số bhxh', 'sổ bhxh', 'số sổ bhxh', 'social insurance' ) ),
		'dependents' => array( 'Người phụ thuộc', array( 'người phụ thuộc', 'số người phụ thuộc', 'dependents', 'giảm trừ gia cảnh' ) ),
		'office'     => array( 'Văn phòng', array( 'văn phòng', 'chi nhánh', 'cơ sở', 'office', 'branch', 'location' ) ),
		'manager'    => array( 'Quản lý trực tiếp', array( 'quản lý', 'quản lý trực tiếp', 'manager', 'line manager', 'cấp trên' ) ),
		'bank'       => array( 'Ngân hàng', array( 'ngân hàng', 'bank', 'số tài khoản', 'stk' ) ),
		'status'     => array( 'Tình trạng', array( 'tình trạng', 'trạng thái', 'status', 'employment status' ) ),
		'role'       => array( 'Vai trò trong nền tảng', array( 'vai trò', 'quyền', 'role', 'phân quyền' ) ),
		'note'       => array( 'Ghi chú', array( 'ghi chú', 'ghi chu', 'note', 'notes', 'remark' ) ),
	);
}

/** Bỏ dấu tiếng Việt + chữ thường, để so tên cột cho dễ. */
function khh_slug_vi( $s ) {
	$s = trim( mb_strtolower( (string) $s, 'UTF-8' ) );
	$map = array(
		'a' => 'áàảãạăắằẳẵặâấầẩẫậ',
		'e' => 'éèẻẽẹêếềểễệ',
		'i' => 'íìỉĩị',
		'o' => 'óòỏõọôốồổỗộơớờởỡợ',
		'u' => 'úùủũụưứừửữự',
		'y' => 'ýỳỷỹỵ',
		'd' => 'đ',
	);
	foreach ( $map as $to => $chars ) {
		$s = preg_replace( '/[' . $chars . ']/u', $to, $s );
	}
	return preg_replace( '/\s+/', ' ', preg_replace( '/[^a-z0-9 ]/u', ' ', $s ) );
}

/** Đoán cột nào ứng với trường nào. */
function khh_guess_column( $header ) {
	$h   = khh_slug_vi( $header );
	$out = '';
	foreach ( khh_import_fields() as $key => $def ) {
		foreach ( $def[1] as $alias ) {
			if ( khh_slug_vi( $alias ) === $h ) {
				return $key;
			}
		}
	}
	return $out;
}

/** Mảng dòng → văn bản CSV, để các bước sau của trình nhập mang theo như với file CSV. */
function khh_rows_to_csv( $rows ) {
	$fh = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	foreach ( $rows as $row ) {
		fputcsv( $fh, $row );
	}
	rewind( $fh );
	$out = stream_get_contents( $fh );
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return (string) $out;
}

/** Tách CSV: tự nhận dấu phân cách , ; hoặc tab, bỏ BOM. */
function khh_parse_csv( $text ) {
	$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
	$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
	$text = trim( $text, "\n" );
	if ( '' === $text ) {
		return array();
	}

	$first = strtok( $text, "\n" );
	$counts = array(
		','  => substr_count( $first, ',' ),
		';'  => substr_count( $first, ';' ),
		"\t" => substr_count( $first, "\t" ),
	);
	arsort( $counts );
	$sep = key( $counts );
	if ( ! $counts[ $sep ] ) {
		$sep = ',';
	}

	$rows = array();
	$fh   = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fwrite( $fh, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	rewind( $fh );
	while ( false !== ( $row = fgetcsv( $fh, 0, $sep ) ) ) {
		if ( 1 === count( $row ) && ( null === $row[0] || '' === trim( (string) $row[0] ) ) ) {
			continue;
		}
		$rows[] = array_map( function ( $c ) { return trim( (string) $c ); }, $row );
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $rows;
}

/** Ngày kiểu Việt (31/12/2026), kiểu Mỹ, hay ISO → Y-m-d. */
function khh_parse_date( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return '';
	}
	if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m ) ) {
		return sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] );
	}
	if ( preg_match( '#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})$#', $v, $m ) ) {
		$y = (int) $m[3];
		if ( $y < 100 ) {
			$y += $y > 50 ? 1900 : 2000;
		}
		return sprintf( '%04d-%02d-%02d', $y, (int) $m[2], (int) $m[1] );
	}
	$ts = strtotime( $v );
	return $ts ? gmdate( 'Y-m-d', $ts ) : '';
}

/** "12.000.000 ₫" → 12000000 */
function khh_parse_money( $v ) {
	$v = preg_replace( '/[^\d]/u', '', (string) $v );
	return '' === $v ? '' : (int) $v;
}

function khh_parse_gender( $v ) {
	$s = khh_slug_vi( $v );
	if ( in_array( $s, array( 'nam', 'male', 'm' ), true ) ) {
		return 'Nam';
	}
	if ( in_array( $s, array( 'nu', 'female', 'f' ), true ) ) {
		return 'Nữ';
	}
	return $v ? $v : 'Nam';
}

function khh_parse_role( $v ) {
	$s = khh_slug_vi( $v );
	if ( in_array( $s, array( 'chu so huu', 'owner', 'giam doc' ), true ) ) {
		return 'owner';
	}
	if ( in_array( $s, array( 'quan tri', 'admin', 'quan tri vien' ), true ) ) {
		return 'admin';
	}
	if ( in_array( $s, array( 'quan ly', 'manager', 'truong phong' ), true ) ) {
		return 'manager';
	}
	return 'staff';
}

function khh_parse_status( $v ) {
	$s = khh_slug_vi( $v );
	if ( in_array( $s, array( 'thu viec', 'probation' ), true ) ) {
		return 'probation';
	}
	if ( in_array( $s, array( 'da nghi', 'nghi viec', 'left', 'terminated', 'inactive' ), true ) ) {
		return 'left';
	}
	return 'active';
}

/**
 * Biến các dòng CSV thành hồ sơ nhân sự và ghi vào kho dữ liệu.
 *
 * @param array $rows    Dòng dữ liệu (không gồm dòng tiêu đề).
 * @param array $mapping Chỉ số cột => tên trường.
 * @param array $opts    match (code_name|code|email|name), update (bool).
 * @return array Thống kê: added, updated, skipped, errors, names.
 */
function khh_import_rows( $rows, $mapping, $opts ) {
	$existing = khh_get_coll( 'staff' );
	$stat     = array(
		'added'   => 0,
		'updated' => 0,
		'skipped' => 0,
		'errors'  => array(),
		'names'   => array(),
	);
	$match  = isset( $opts['match'] ) ? $opts['match'] : 'code';
	$update = ! empty( $opts['update'] );

	foreach ( $rows as $n => $row ) {
		$data = array();
		foreach ( $mapping as $idx => $field ) {
			if ( '' === $field || ! isset( $row[ $idx ] ) ) {
				continue;
			}
			$data[ $field ] = $row[ $idx ];
		}
		if ( empty( $data['name'] ) ) {
			$stat['skipped']++;
			continue;
		}

		/* Chỉ ghi Vai trò và Tình trạng khi file thật sự có cột đó. Nếu không,
		   lần nhập sau sẽ xoá mất vai trò đã chỉnh tay trong nền tảng. */
		$staff = array( 'name' => sanitize_text_field( $data['name'] ) );
		if ( isset( $data['status'] ) && '' !== trim( (string) $data['status'] ) ) {
			$staff['status'] = khh_parse_status( $data['status'] );
		}
		if ( isset( $data['role'] ) && '' !== trim( (string) $data['role'] ) ) {
			$staff['role'] = khh_parse_role( $data['role'] );
		}
		foreach ( array( 'code', 'title', 'dept', 'unit', 'phone', 'office', 'manager', 'bank', 'taxCode', 'bhxh', 'note' ) as $k ) {
			if ( isset( $data[ $k ] ) && '' !== $data[ $k ] ) {
				$staff[ $k ] = sanitize_text_field( $data[ $k ] );
			}
		}
		if ( isset( $data['email'] ) && is_email( $data['email'] ) ) {
			$staff['email'] = sanitize_email( $data['email'] );
		}
		foreach ( array( 'dob', 'start', 'official' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$d = khh_parse_date( $data[ $k ] );
				if ( $d ) {
					$staff[ $k ] = $d;
				}
			}
		}
		foreach ( array( 'salary', 'allowance', 'dependents' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$num = khh_parse_money( $data[ $k ] );
				if ( '' !== $num ) {
					$staff[ $k ] = $num;
				}
			}
		}
		if ( isset( $data['gender'] ) ) {
			$staff['gender'] = khh_parse_gender( $data['gender'] );
		}

		// Tìm hồ sơ đã có.
		$found_id = '';
		foreach ( $existing as $id => $old ) {
			$same = false;
			if ( 'code_name' === $match ) {
				/* Khớp theo mã; hồ sơ cũ chưa có mã thì dò theo họ tên, để những người
				   nhập tay lúc đầu (Giám đốc, Admin…) không bị tạo trùng. */
				if ( ! empty( $staff['code'] ) && ! empty( $old['code'] ) ) {
					$same = khh_slug_vi( $old['code'] ) === khh_slug_vi( $staff['code'] );
				} elseif ( empty( $old['code'] ) && ! empty( $old['name'] ) ) {
					$same = khh_slug_vi( $old['name'] ) === khh_slug_vi( $staff['name'] );
				}
			} elseif ( 'code' === $match && ! empty( $staff['code'] ) && ! empty( $old['code'] ) ) {
				$same = khh_slug_vi( $old['code'] ) === khh_slug_vi( $staff['code'] );
			} elseif ( 'email' === $match && ! empty( $staff['email'] ) && ! empty( $old['email'] ) ) {
				$same = strtolower( $old['email'] ) === strtolower( $staff['email'] );
			} elseif ( 'name' === $match && ! empty( $old['name'] ) ) {
				$same = khh_slug_vi( $old['name'] ) === khh_slug_vi( $staff['name'] );
			}
			if ( $same ) {
				$found_id = $id;
				break;
			}
		}

		if ( $found_id ) {
			if ( ! $update ) {
				$stat['skipped']++;
				continue;
			}
			$merged = array_merge( $existing[ $found_id ], $staff );
			khh_put_doc( 'staff', $found_id, $merged );
			$existing[ $found_id ] = $merged;
			$stat['updated']++;
			$stat['names'][] = $staff['name'] . ' (cập nhật)';
			continue;
		}

		$staff += array(
			'status'    => 'active',
			'role'      => 'staff',
			'worktime'  => 'Full-time',
			'type'      => 'Full-Time Employee',
			'leaveLeft' => 12,
			'leaveYear' => 12,
			'color'     => '',
		);
		$id = 'imp_' . substr( md5( $staff['name'] . '|' . ( isset( $staff['code'] ) ? $staff['code'] : $n ) ), 0, 10 );
		khh_put_doc( 'staff', $id, $staff );
		$existing[ $id ] = $staff;
		$stat['added']++;
		$stat['names'][] = $staff['name'];
	}
	return $stat;
}

/* ==================================================================== *
 * Đọc thẳng từ cơ sở dữ liệu — khi hệ thống nhân sự cũ nằm cùng WordPress
 * ==================================================================== */

/** Mọi bảng trong cơ sở dữ liệu, kèm số dòng. Chỉ đọc, không đụng gì. */
function khh_db_tables() {
	global $wpdb;
	$names = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore
	$out   = array();
	foreach ( (array) $names as $t ) {
		if ( $t === khh_table() ) {
			continue;
		}
		$out[ $t ] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $t ) . '`' ); // phpcs:ignore
	}
	return $out;
}

/** Chỉ chấp nhận tên bảng có thật — chặn mọi thứ chèn vào câu lệnh. */
function khh_db_table_ok( $table ) {
	global $wpdb;
	$names = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore
	return in_array( (string) $table, (array) $names, true ) && (string) $table !== khh_table();
}

/** Bảng nào trông giống dữ liệu nhân sự thì đưa lên đầu. */
function khh_db_looks_like_hr( $table ) {
	return (bool) preg_match( '/(hr|hrm|erp|employee|staff|personnel|nhan_?su|nhanvien|payroll|attendance|cham_?cong)/i', $table );
}

/**
 * Cột của một nguồn dữ liệu.
 *
 * @param string $src table | cpt | users
 * @param string $key tên bảng, hoặc tên kiểu nội dung
 */
function khh_src_columns( $src, $key ) {
	global $wpdb;
	if ( 'table' === $src ) {
		if ( ! khh_db_table_ok( $key ) ) {
			return array();
		}
		return (array) $wpdb->get_col( 'SHOW COLUMNS FROM `' . esc_sql( $key ) . '`' ); // phpcs:ignore
	}
	if ( 'users' === $src ) {
		$meta = (array) $wpdb->get_col( // phpcs:ignore
			"SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE meta_key NOT LIKE '\\_%' ORDER BY meta_key LIMIT 60"
		);
		return array_merge( array( 'ID', 'user_login', 'user_email', 'display_name', 'user_registered' ), $meta );
	}
	if ( 'cpt' === $src ) {
		$meta = (array) $wpdb->get_col( // phpcs:ignore
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE p.post_type = %s AND pm.meta_key NOT LIKE '\\_%%'
				 ORDER BY pm.meta_key LIMIT 60",
				$key
			)
		);
		return array_merge( array( 'ID', 'post_title', 'post_name', 'post_date', 'post_status' ), $meta );
	}
	return array();
}

/**
 * Dòng dữ liệu của một nguồn, xếp đúng thứ tự cột của khh_src_columns().
 *
 * @param int $limit 0 = lấy hết.
 */
function khh_src_rows( $src, $key, $limit = 0 ) {
	global $wpdb;
	$cols = khh_src_columns( $src, $key );
	if ( ! $cols ) {
		return array();
	}
	$rows = array();

	if ( 'table' === $src ) {
		$sql  = 'SELECT * FROM `' . esc_sql( $key ) . '`' . ( $limit ? ' LIMIT ' . (int) $limit : '' );
		$data = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
		foreach ( (array) $data as $r ) {
			$line = array();
			foreach ( $cols as $c ) {
				$line[] = isset( $r[ $c ] ) ? (string) $r[ $c ] : '';
			}
			$rows[] = $line;
		}
		return $rows;
	}

	if ( 'users' === $src ) {
		$users = get_users( array( 'number' => $limit ? $limit : 1000 ) );
		foreach ( $users as $u ) {
			$line = array();
			foreach ( $cols as $c ) {
				if ( in_array( $c, array( 'ID', 'user_login', 'user_email', 'display_name', 'user_registered' ), true ) ) {
					$line[] = (string) $u->$c;
				} else {
					$line[] = (string) get_user_meta( $u->ID, $c, true );
				}
			}
			$rows[] = $line;
		}
		return $rows;
	}

	if ( 'cpt' === $src ) {
		$posts = get_posts(
			array(
				'post_type'   => $key,
				'numberposts' => $limit ? $limit : 1000,
				'post_status' => 'any',
			)
		);
		foreach ( $posts as $p ) {
			$line = array();
			foreach ( $cols as $c ) {
				if ( in_array( $c, array( 'ID', 'post_title', 'post_name', 'post_date', 'post_status' ), true ) ) {
					$line[] = (string) $p->$c;
				} else {
					$line[] = (string) get_post_meta( $p->ID, $c, true );
				}
			}
			$rows[] = $line;
		}
		return $rows;
	}
	return $rows;
}

/** Đoán cột cho nguồn cơ sở dữ liệu: tên cột kỹ thuật thường là tiếng Anh không dấu. */
function khh_guess_db_column( $col ) {
	$guess = khh_guess_column( $col );
	if ( $guess ) {
		return $guess;
	}
	$c = strtolower( preg_replace( '/[^a-z0-9]+/i', '_', (string) $col ) );
	$map = array(
		'name'       => array( 'display_name', 'post_title', 'full_name', 'fullname', 'employee_name', 'ho_ten', 'hoten', 'ten_nhan_vien' ),
		'code'       => array( 'employee_id', 'employee_code', 'emp_code', 'emp_id', 'staff_id', 'ma_nv', 'manv', 'ma_nhan_vien', 'post_name' ),
		'unit'       => array( 'business_unit', 'company', 'company_name', 'brand', 'khoi', 'mang', 'mang_kinh_doanh' ),
		'email'      => array( 'user_email', 'email_address', 'work_email', 'personal_email' ),
		'phone'      => array( 'phone_number', 'mobile', 'mobile_number', 'contact_number', 'so_dien_thoai', 'sdt' ),
		'title'      => array( 'designation', 'job_title', 'position', 'chuc_danh', 'chuc_vu' ),
		'dept'       => array( 'department', 'dept_id', 'department_name', 'team', 'phong_ban', 'bo_phan' ),
		'dob'        => array( 'date_of_birth', 'birth_date', 'birthday', 'dob', 'ngay_sinh' ),
		'gender'     => array( 'gender', 'sex', 'gioi_tinh' ),
		'start'      => array( 'hiring_date', 'joining_date', 'date_of_joining', 'hire_date', 'start_date', 'user_registered', 'ngay_vao_lam', 'ngay_vao' ),
		'salary'     => array( 'pay_rate', 'basic_salary', 'salary', 'base_salary', 'luong', 'luong_co_ban' ),
		'allowance'  => array( 'allowance', 'phu_cap', 'bonus' ),
		'status'     => array( 'status', 'employment_status', 'tinh_trang', 'post_status' ),
		'office'     => array( 'location', 'branch', 'office', 'work_location', 'chi_nhanh', 'co_so' ),
		'bank'       => array( 'bank_name', 'bank', 'account_number', 'so_tai_khoan' ),
		'taxCode'    => array( 'tax_code', 'tax_id', 'ma_so_thue', 'mst' ),
		'bhxh'       => array( 'social_insurance', 'insurance_number', 'so_bhxh', 'bhxh' ),
		'dependents' => array( 'dependents', 'nguoi_phu_thuoc' ),
		'manager'    => array( 'reporting_to', 'manager', 'line_manager', 'quan_ly' ),
		'note'       => array( 'note', 'notes', 'remark', 'description', 'ghi_chu' ),
	);
	foreach ( $map as $field => $aliases ) {
		if ( in_array( $c, $aliases, true ) ) {
			return $field;
		}
	}
	return '';
}

/* ------------------------------------------------------------------ *
 * Trang quản trị
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'khh_import_menu', 23 );
function khh_import_menu() {
	add_submenu_page( 'khh-platform', 'Nhập / Xuất dữ liệu', 'Nhập / Xuất dữ liệu', 'manage_options', 'khh-import', 'khh_import_page' );
}

/** Xuất toàn bộ hồ sơ nhân sự ra CSV. */
add_action( 'admin_post_khh_export_staff', 'khh_export_staff' );
function khh_export_staff() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'khh_import' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	$fields = khh_import_fields();
	$keys   = array_keys( $fields );

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=nhan-su-khh-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fputcsv( $out, array_map( function ( $k ) use ( $fields ) { return $fields[ $k ][0]; }, $keys ) );
	foreach ( khh_get_coll( 'staff' ) as $row ) {
		$line = array();
		foreach ( $keys as $k ) {
			$line[] = isset( $row[ $k ] ) ? $row[ $k ] : '';
		}
		fputcsv( $out, $line );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

function khh_import_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Không đủ quyền.' );
	}

	$step   = 1;
	$rows   = array();
	$header = array();
	$stat   = null;
	$csv    = '';
	$err    = '';

	$src    = '';
	$srckey = '';

	if ( isset( $_POST['khh_import_step'] ) && check_admin_referer( 'khh_import' ) ) {
		$step   = (int) $_POST['khh_import_step'];
		$src    = isset( $_POST['src'] ) ? sanitize_key( wp_unslash( $_POST['src'] ) ) : '';
		$srckey = isset( $_POST['srckey'] ) ? sanitize_text_field( wp_unslash( $_POST['srckey'] ) ) : '';

		/* Bước 1 gửi lên một ô duy nhất dạng "loại|khoá" để không cần JavaScript. */
		if ( ! $src && isset( $_POST['srcsel'] ) ) {
			$parts  = explode( '|', sanitize_text_field( wp_unslash( $_POST['srcsel'] ) ), 2 );
			$src    = sanitize_key( $parts[0] );
			$srckey = isset( $parts[1] ) ? $parts[1] : '';
		}

		if ( in_array( $src, array( 'table', 'cpt', 'users' ), true ) ) {
			/* Nguồn là cơ sở dữ liệu: đọc thẳng, xem trước 8 dòng, nhập thì lấy hết. */
			$header = khh_src_columns( $src, $srckey );
			$rows   = khh_src_rows( $src, $srckey, 3 === $step ? 0 : 8 );
			$total  = 3 === $step ? count( $rows ) : null;
		} else {
			if ( ! empty( $_FILES['khh_file']['tmp_name'] ) ) { // phpcs:ignore
				$tmp  = $_FILES['khh_file']['tmp_name']; // phpcs:ignore
				$fname = isset( $_FILES['khh_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['khh_file']['name'] ) ) : ''; // phpcs:ignore
				if ( preg_match( '/\.xlsx$/i', $fname ) ) {
					if ( ! khh_xlsx_ready() ) {
						$err = 'Máy chủ chưa bật ZipArchive nên chưa đọc được .xlsx. '
							. 'Mở file bằng Excel rồi Lưu thành CSV UTF-8, hoặc nhờ bên lưu trữ bật phần mở rộng zip cho PHP.';
					} else {
						$xrows = khh_parse_xlsx( $tmp );
						if ( ! $xrows ) {
							$err = 'Không đọc được nội dung file Excel này. Thử mở lại bằng Excel và lưu thành .xlsx hoặc .csv.';
						} else {
							$csv = khh_rows_to_csv( $xrows );
						}
					}
				} else {
					$csv = file_get_contents( $tmp ); // phpcs:ignore
				}
			} elseif ( isset( $_POST['khh_csv'] ) ) {
				$csv = wp_unslash( $_POST['khh_csv'] ); // phpcs:ignore
			}
			$all = khh_parse_csv( $csv );
			if ( $all ) {
				$header = array_shift( $all );
				$rows   = $all;
			}
		}

		if ( 3 === $step && $rows ) {
			$mapping = array();
			$posted  = isset( $_POST['map'] ) ? (array) $_POST['map'] : array(); // phpcs:ignore
			foreach ( $posted as $i => $f ) {
				$mapping[ (int) $i ] = sanitize_key( $f );
			}
			$stat = khh_import_rows(
				$rows,
				$mapping,
				array(
					'match'  => isset( $_POST['match'] ) ? sanitize_key( wp_unslash( $_POST['match'] ) ) : 'code_name',
					'update' => ! empty( $_POST['update_existing'] ),
				)
			);
			$step = 4;
		} elseif ( $rows ) {
			$step = 2;
		} else {
			$step = 1;
		}
	}
	?>
	<div class="wrap">
		<h1>Nền tảng K&amp;H — Nhập / Xuất dữ liệu</h1>

		<?php if ( $err ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $err ); ?></p></div>
		<?php endif; ?>

		<?php if ( 4 === $step && $stat ) : ?>
			<div class="notice notice-success">
				<p><strong>Xong.</strong> Thêm mới <?php echo (int) $stat['added']; ?> người,
				cập nhật <?php echo (int) $stat['updated']; ?>, bỏ qua <?php echo (int) $stat['skipped']; ?>.</p>
			</div>
			<?php if ( $stat['names'] ) : ?>
				<p><?php echo esc_html( implode( ' · ', array_slice( $stat['names'], 0, 60 ) ) ); ?></p>
			<?php endif; ?>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=khh-platform' ) ); ?>">Mở nền tảng xem kết quả</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=khh-accounts' ) ); ?>">Tạo tài khoản đăng nhập cho họ</a></p>
		<?php endif; ?>

		<?php if ( 2 === $step && $rows ) : ?>
			<h2>Bước 2 — Soát lại cột</h2>
			<p>
			<?php if ( $src ) : ?>
				Nguồn: <code><?php echo esc_html( $srckey ? $srckey : 'Người dùng WordPress' ); ?></code> —
				xem trước <strong><?php echo count( $rows ); ?></strong> dòng đầu, khi nhập sẽ lấy hết.
			<?php else : ?>
				Đọc được <strong><?php echo count( $rows ); ?></strong> dòng.
			<?php endif; ?>
			Mỗi cột chọn xem là thông tin gì; cột nào không cần thì để <em>— bỏ qua —</em>.</p>
			<form method="post">
				<?php wp_nonce_field( 'khh_import' ); ?>
				<input type="hidden" name="khh_import_step" value="3">
				<?php if ( $src ) : ?>
					<input type="hidden" name="src" value="<?php echo esc_attr( $src ); ?>">
					<input type="hidden" name="srckey" value="<?php echo esc_attr( $srckey ); ?>">
				<?php else : ?>
					<input type="hidden" name="khh_csv" value="<?php echo esc_attr( $csv ); ?>">
				<?php endif; ?>
				<div style="overflow-x:auto">
				<table class="widefat striped">
					<thead>
						<tr>
						<?php foreach ( $header as $i => $h ) : ?>
							<th style="min-width:170px">
								<div style="font-weight:600;margin-bottom:4px"><?php echo esc_html( $h ); ?></div>
								<select name="map[<?php echo (int) $i; ?>]">
									<option value="">— bỏ qua —</option>
									<?php
									$guess = $src ? khh_guess_db_column( $h ) : khh_guess_column( $h );
									foreach ( khh_import_fields() as $key => $def ) :
										?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $guess, $key ); ?>>
											<?php echo esc_html( $def[0] ); ?></option>
									<?php endforeach; ?>
								</select>
							</th>
						<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( array_slice( $rows, 0, 8 ) as $r ) : ?>
						<tr>
						<?php foreach ( $header as $i => $h ) : ?>
							<td><?php echo esc_html( isset( $r[ $i ] ) ? $r[ $i ] : '' ); ?></td>
						<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
				<table class="form-table" role="presentation">
					<tr>
						<th>Nhận ra người đã có bằng</th>
						<td>
							<label><input type="radio" name="match" value="code_name" checked> Mã nhân sự, hồ sơ chưa có mã thì dò theo họ tên</label><br>
							<label><input type="radio" name="match" value="code"> Chỉ mã nhân sự</label><br>
							<label><input type="radio" name="match" value="email"> Email</label><br>
							<label><input type="radio" name="match" value="name"> Họ và tên</label>
							<p class="description">Cách đầu hợp với lần nhập thứ hai trở đi: những hồ sơ bạn tạo tay lúc đầu
								(Giám đốc, Admin…) chưa có mã sẽ được nhận ra theo tên thay vì bị tạo trùng.</p>
						</td>
					</tr>
					<tr>
						<th>Người đã có trong hệ thống</th>
						<td><label><input type="checkbox" name="update_existing" value="1" checked>
							Cập nhật thông tin mới đè lên</label>
							<p class="description">Bỏ tích thì giữ nguyên hồ sơ cũ, chỉ thêm người mới.</p></td>
					</tr>
				</table>
				<?php submit_button( $src ? 'Nhập toàn bộ vào hệ thống' : 'Nhập ' . count( $rows ) . ' dòng vào hệ thống' ); ?>
			</form>
		<?php endif; ?>

		<?php if ( 1 === $step || 4 === $step ) : ?>
			<h2>Nhập thẳng từ cơ sở dữ liệu của site này</h2>
			<p>Nếu hệ thống nhân sự cũ chạy ngay trên WordPress này thì không cần xuất file —
			chọn thẳng nơi nó đang lưu dữ liệu. Plugin chỉ <strong>đọc</strong>, không sửa gì của hệ thống kia.</p>

			<?php
			$tables = khh_db_tables();
			$hr     = array_filter( array_keys( $tables ), 'khh_db_looks_like_hr' );
			$cpts   = array();
			foreach ( get_post_types( array(), 'objects' ) as $pt ) {
				if ( in_array( $pt->name, array( 'revision', 'nav_menu_item', 'attachment', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' ), true ) ) {
					continue;
				}
				$n = (int) wp_count_posts( $pt->name )->publish + (int) wp_count_posts( $pt->name )->draft;
				$cpts[ $pt->name ] = $pt->labels->name . ' (' . $n . ')';
			}
			?>

			<form method="post">
				<?php wp_nonce_field( 'khh_import' ); ?>
				<input type="hidden" name="khh_import_step" value="2">
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="srcsel">Lấy dữ liệu từ</label></th>
						<td>
							<select name="srcsel" id="srcsel" style="min-width:420px">
								<optgroup label="Người dùng WordPress">
									<option value="users|">Tất cả tài khoản WordPress (kèm trường tuỳ biến)</option>
								</optgroup>
								<?php if ( $hr ) : ?>
								<optgroup label="Bảng trông giống dữ liệu nhân sự">
									<?php foreach ( $hr as $t ) : ?>
										<option value="table|<?php echo esc_attr( $t ); ?>">
											<?php echo esc_html( $t . ' — ' . $tables[ $t ] . ' dòng' ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<?php endif; ?>
								<optgroup label="Kiểu nội dung (custom post type)">
									<?php foreach ( $cpts as $k => $label ) : ?>
										<option value="cpt|<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label . ' — ' . $k ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<optgroup label="Mọi bảng trong cơ sở dữ liệu">
									<?php foreach ( $tables as $t => $n ) : ?>
										<option value="table|<?php echo esc_attr( $t ); ?>">
											<?php echo esc_html( $t . ' — ' . $n . ' dòng' ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							</select>
							<p class="description">Không chắc nó nằm ở đâu? Chọn thử từng bảng —
							bước sau chỉ xem trước, chưa ghi gì cả.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Xem thử dữ liệu', 'primary' ); ?>
			</form>

			<hr style="margin:26px 0">

			<h2>Nhập hồ sơ nhân sự từ file</h2>
			<p>Xuất danh sách nhân sự từ phần mềm cũ ra <strong>CSV</strong> (Excel: <em>Save As → CSV UTF-8</em>),
			rồi tải lên hoặc dán thẳng vào ô bên dưới. Dòng đầu phải là tiêu đề cột.
			Hệ thống tự đoán cột theo tên tiếng Việt, bước sau anh soát lại.</p>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'khh_import' ); ?>
				<input type="hidden" name="khh_import_step" value="2">
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="khh_file">Tải file CSV</label></th>
						<td><input type="file" name="khh_file" id="khh_file"
							accept=".csv,.xlsx,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
						<p class="description">Nhận thẳng file Excel <code>.xlsx</code> xuất từ phần mềm nhân sự, hoặc file CSV.
							Lấy bảng tính đầu tiên; ô ngày tháng tự đổi về dạng ngày/tháng/năm.</p></td>
					</tr>
					<tr>
						<th><label for="khh_csv">Hoặc dán dữ liệu</label></th>
						<td><textarea name="khh_csv" id="khh_csv" rows="8" class="large-text code"
								placeholder="Họ và tên,Mã NV,Chức danh,Bộ phận,Email,Ngày vào làm,Lương cơ bản&#10;Quang Thắng,MNV-01,Quản lý dự án,Phòng kỹ thuật,thang@khh.vn,01/03/2021,18.000.000"></textarea>
							<p class="description">Dán thẳng từ Excel cũng được — cột cách nhau bằng Tab.</p></td>
					</tr>
				</table>
				<?php submit_button( 'Đọc dữ liệu', 'primary' ); ?>
			</form>

			<hr style="margin:26px 0">

			<h2>Xuất dữ liệu đang có</h2>
			<p>Tải toàn bộ hồ sơ nhân sự ra CSV để đối chiếu hoặc sao lưu.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="khh_export_staff">
				<?php wp_nonce_field( 'khh_import' ); ?>
				<?php submit_button( 'Tải CSV hồ sơ nhân sự', 'secondary', 'submit', false ); ?>
			</form>

			<hr style="margin:26px 0">

			<h2>Để hệ thống cũ tự đẩy sang</h2>
			<p>Nếu phần mềm nhân sự cũ gọi được API, nó có thể ghi thẳng vào đây, không cần xuất file:</p>
			<pre style="background:#f6f7f7;padding:12px;border:1px solid #dcdcde;overflow:auto">POST <?php echo esc_html( rest_url( 'khh/v1/doc' ) ); ?>

Authorization: Basic &lt;tên đăng nhập:application password&gt;
Content-Type: application/json

{
  "coll": "staff",
  "id":   "MNV-01",
  "data": { "name": "Quang Thắng", "code": "MNV-01", "title": "Quản lý dự án",
            "dept": "Phòng kỹ thuật", "email": "thang@khh.vn",
            "start": "2021-03-01", "salary": 18000000, "role": "admin" }
}</pre>
			<p><strong>Application password</strong> tạo trong <em>Người dùng → Hồ sơ → Application Passwords</em>
			của một tài khoản Quản trị. Ghi vào nhóm <code>staff</code> đòi quyền Quản trị trở lên.
			Cùng cách đó đẩy được cả <code>attendance</code> (dữ liệu chấm công) và các nhóm khác —
			xem README để biết cấu trúc từng nhóm.</p>
		<?php endif; ?>
	</div>
	<?php
}
