<?php
/**
 * VCG_Nap — đọc CSV xuất thẳng từ Google Sheets và chuẩn hoá về dữ liệu dùng được.
 *
 * VÌ SAO CÓ TỆP NÀY
 * Anh Thắng xuất CSV từ Sheet rồi nạp lên web. Nghĩa là bộ đọc phải chịu được ĐÚNG thứ Sheet
 * nhả ra, không phải thứ mình mong nó nhả ra: tiêu đề tự đặt kiểu `Cột 7`, ô trống, khối tháng
 * xếp dọc, và cùng một giá trị viết ba kiểu khác nhau.
 *
 * MỌI HÀM Ở ĐÂY ĐỀU THUẦN — vào là chuỗi, ra là mảng. Không đụng cơ sở dữ liệu, không đụng
 * WordPress. Nhờ vậy thử được thẳng bằng chính hai tệp CSV thật của anh Thắng, không cần dựng
 * máy chủ. Xem tools/test/kiem-nap-cong.php.
 *
 * @package vhcp-cong
 */

if ( ! defined( 'ABSPATH' ) ) { if ( ! defined( 'VCG_TEST' ) ) { exit; } }

class VCG_Nap {

	/* ==========================================================================================
	 *  SHEET NHÂN VIÊN — `NV_NguonCongT`
	 *
	 *  Tiêu đề trong CSV phần lớn là chỗ giữ chỗ (`Cột 2`, `Cột 7`…), nên KHÔNG khớp được theo
	 *  tên. Phải đọc THEO VỊ TRÍ. Đó là lý do bảng dưới ghi rõ số cột, và cũng là lý do đổi thứ
	 *  tự cột trong Sheet là hỏng im lặng — ghi ra đây để người sau biết mà không đổi.
	 *
	 *  Cột 15–29 trống sạch trong toàn bộ 245 dòng nên bỏ; thêm lại khi nào Sheet có dữ liệu.
	 * ========================================================================================== */
	const NV_COT = array(
		0  => 'ma_nv',
		1  => 'tao_luc',
		2  => 'ho_ten',
		3  => 'mien',
		4  => 'phong_ban',
		5  => 'loai_hinh',
		6  => 'gioi_tinh',
		7  => 'ngay_sinh',
		8  => 'sdt',
		9  => 'cccd',
		10 => 'ngay_cap_cccd',
		11 => 'don_vi',
		12 => 'tinh_thanh',
		13 => 'loai_hop_dong',
	);

	/**
	 * Gộp các cách viết khác nhau của cùng một giá trị.
	 *
	 * Anh Thắng chốt 25/08/2026: *"gộp lại khi nạp"*.
	 *
	 * Trong 245 dòng thật có hai chỗ lệch:
	 *     "Máy tự động"  và  "Máy Tự Động"      — khác hoa thường
	 *     "Full-time"    và  "Full time"        — khác dấu gạch
	 *
	 * Nạp nguyên trạng thì bộ lọc hiện BA loại hợp đồng thay vì hai, và mọi thống kê chia đôi.
	 * Người nhìn báo cáo không có cách nào biết mình đang thiếu một nửa.
	 *
	 * ⚠️ Gộp bằng cách hạ hoa thường + bỏ dấu gạch/khoảng trắng thừa RỒI TRA BẢNG, chứ không
	 *    sửa chuỗi bằng tay từng ca. Sửa từng ca là lần sau thêm một cách viết mới lại lọt.
	 */
	public static function gop( $s ) {
		$s = trim( preg_replace( '/\s+/u', ' ', (string) $s ) );
		if ( '' === $s ) { return ''; }
		/* Khoá tra: hạ hoa thường, bỏ mọi dấu gạch và khoảng trắng. "Full time" và "Full-time"
		   và "FULL-TIME" đều thành "fulltime". */
		$khoa = preg_replace( '/[\s\-_]+/u', '', self::ha_hoa( $s ) );
		/* ⚠️ KHOÁ TRA PHẢI CÒN DẤU TIẾNG VIỆT. Hạ hoa thường KHÔNG bỏ dấu, nên "Máy Tự Động"
		   thành "máytựđộng" chứ không phải "maytudong". Viết khoá không dấu là bảng không bao
		   giờ khớp, và hàm lặng lẽ trả về nguyên trạng — đúng lỗi phép thử vừa bắt được. */
		$bang = array(
			'fulltime'    => 'Full-time',
			'parttime'    => 'Part-time',
			'máytựđộng'   => 'Máy tự động',
			'khuvuichơi'  => 'Khu vui chơi',
		);
		return isset( $bang[ $khoa ] ) ? $bang[ $khoa ] : $s;
	}

	/** Hạ hoa thường có dấu tiếng Việt. mb_strtolower có sẵn từ PHP 4.6, nhưng đề phòng thiếu. */
	private static function ha_hoa( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}

	/**
	 * Ngày kiểu Sheet -> 'yyyy-mm-dd', hoặc null nếu không phải ngày.
	 *
	 * Sheet nhả ra hai khuôn khác nhau trong cùng một bảng tính: sheet nhân viên dùng
	 * `dd/mm/yyyy`, sheet cơ sở dùng `yyyy-mm-dd`. Nhận cả hai.
	 *
	 * ⚠️ `dd/mm` chứ KHÔNG phải `mm/dd`. Đọc nhầm chiều thì 03/04 thành mùng 4 tháng 3, và mọi
	 *    ngày có ngày ≤ 12 đều sai mà KHÔNG có gì báo — chỉ ngày > 12 mới lộ ra. Đây là kiểu lỗi
	 *    nằm im mấy tháng rồi mới thấy khi đối chiếu lương.
	 */
	public static function ngay( $s ) {
		$s = trim( (string) $s );
		if ( '' === $s ) { return null; }
		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m ) ) {
			return self::ghep_ngay( (int) $m[1], (int) $m[2], (int) $m[3] );
		}
		if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})#', $s, $m ) ) {
			return self::ghep_ngay( (int) $m[3], (int) $m[2], (int) $m[1] );
		}
		return null;
	}

	private static function ghep_ngay( $y, $m, $d ) {
		if ( ! checkdate( $m, $d, $y ) ) { return null; }
		return sprintf( '%04d-%02d-%02d', $y, $m, $d );
	}

	/**
	 * 'HH:mm' hoặc 'HH:mm:ss' -> số giây trong ngày; null nếu không phải giờ.
	 *
	 * Giữ giờ bằng SỐ GIÂY chứ không phải chuỗi, vì luật giờ vào/giờ ra phải so sánh được, và
	 * ca đêm cần cộng 86400 để trải phẳng trục thời gian. Giữ chuỗi thì 06:00 luôn "sớm hơn"
	 * 22:00 và ca đêm bị đảo thành 16 tiếng ban ngày — đúng lỗi bản Apps Script phải viết hàm
	 * riêng để né.
	 */
	public static function giay( $s ) {
		$s = trim( (string) $s );
		if ( '' === $s ) { return null; }
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $s, $m ) ) { return null; }
		$h = (int) $m[1]; $p = (int) $m[2]; $g = isset( $m[3] ) ? (int) $m[3] : 0;
		if ( $h > 23 || $p > 59 || $g > 59 ) { return null; }
		return $h * 3600 + $p * 60 + $g;
	}

	/**
	 * Mọi mốc giờ có trong MỘT ô -> mảng số giây. Ô sạch cho một phần tử, ô bẩn cho nhiều.
	 *
	 * 🔴 VÌ SAO CẦN: tệp `CS_VP_KHHCM_1` có ô Giờ Vào ghi `08:30 13:23:38` — hai mốc trong một ô,
	 *    do người ta gõ tay giờ ca vào cạnh giờ quét thật. `giay()` khớp cả chuỗi nên trả null,
	 *    và cả buổi làm của người đó BIẾN MẤT khỏi bảng công mà không có lỗi nào hiện ra.
	 *    Mất im lặng đúng vào ô quyết định tiền lương là kiểu hỏng tệ nhất ở đây.
	 *
	 * Tách theo khoảng trắng rồi đọc từng mảnh. Mảnh nào không phải giờ thì bỏ, KHÔNG đoán.
	 */
	public static function moc_gio( $s ) {
		$s = trim( (string) $s );
		if ( '' === $s ) { return array(); }
		$ra = array();
		foreach ( preg_split( '/[\s,;]+/u', $s ) as $manh ) {
			$g = self::giay( $manh );
			if ( null !== $g ) { $ra[] = $g; }
		}
		return $ra;
	}

	/**
	 * Ô CÓ CHỮ SỐ VÀ DẤU HAI CHẤM MÀ KHÔNG RA MỐC NÀO — tức có người định ghi giờ mà máy không
	 * hiểu. Dùng để BÁO RA MÀN HÌNH thay vì lặng lẽ bỏ qua.
	 */
	public static function o_hong( $s ) {
		$s = trim( (string) $s );
		if ( '' === $s ) { return false; }
		if ( ! preg_match( '/\d/', $s ) ) { return false; }
		return ! self::moc_gio( $s );
	}

	/**
	 * Cột "Đơn vị làm việc" chứa NHIỀU đơn vị ngăn bằng dấu phẩy -> tách thành mảng.
	 *
	 * 🔴 Trong 245 dòng thật có 8 ô kiểu này, ô nhiều nhất SÁU đơn vị:
	 *    `Funzone ADV Aeon Mall Tân Phú, Tutu Lotte Mart Gò Vấp, Tutu Train Aeon Mall Tân An, …`
	 *    Giữ nguyên cả chuỗi là đẻ ra một "đơn vị" ảo không tồn tại ở đâu cả, và người đó không
	 *    thuộc về cơ sở nào trong số sáu cơ sở họ thật sự làm. Đúng chỗ anh Thắng dặn:
	 *    *"nhân viên có thể làm ở nhiều gian qua cột đơn vị làm việc"*.
	 */
	public static function tach_don_vi( $s ) {
		$ra = array();
		foreach ( explode( ',', (string) $s ) as $x ) {
			$x = trim( preg_replace( '/\s+/u', ' ', $x ) );
			if ( '' !== $x ) { $ra[ $x ] = 1; }
		}
		return array_keys( $ra );
	}

	/**
	 * Đọc CSV nhân viên -> array( 'nguoi' => [...], 'gan' => [...] ).
	 *
	 * MỘT NGƯỜI ↔ NHIỀU CƠ SỞ. Anh Thắng 25/08/2026: *"nhân viên có thể làm ở nhiều gian qua cột
	 * đơn vị làm việc"*. Trong 245 dòng thật có 215 mã, 27 mã lặp, và 21 người có từ hai đơn vị
	 * trở lên.
	 *
	 * 🔴 Vì vậy KHÔNG lấy mã NV làm khoá duy nhất rồi ghi đè. Đè là mỗi người còn đúng một cơ sở
	 *    — cơ sở của dòng cuối cùng — và mất hết phần còn lại. Hỏng kiểu này không báo gì cả,
	 *    chỉ là mai mốt thấy thiếu người ở cơ sở nào đó mà không hiểu vì sao.
	 *
	 * Trả về hai mảng: hồ sơ người (một dòng một người) và bảng gán cơ sở (một dòng một cặp
	 * người–đơn vị).
	 */
	public static function doc_nhan_vien( $hang ) {
		$nguoi = array();
		$gan   = array();
		$bo    = 0;
		$dau   = true;
		foreach ( $hang as $r ) {
			if ( $dau ) { $dau = false; continue; }          // bỏ hàng tiêu đề
			$o = self::lay_o( $r );
			if ( '' === $o['ma_nv'] ) { $bo++; continue; }

			$ma = $o['ma_nv'];
			if ( ! isset( $nguoi[ $ma ] ) ) {
				$nguoi[ $ma ] = array(
					'ma_nv'         => $ma,
					'ho_ten'        => $o['ho_ten'],
					'mien'          => self::gop( $o['mien'] ),
					'phong_ban'     => self::gop( $o['phong_ban'] ),
					'loai_hinh'     => self::gop( $o['loai_hinh'] ),
					'gioi_tinh'     => self::gop( $o['gioi_tinh'] ),
					'ngay_sinh'     => self::ngay( $o['ngay_sinh'] ),
					'sdt'           => $o['sdt'],
					'cccd'          => $o['cccd'],
					'ngay_cap_cccd' => self::ngay( $o['ngay_cap_cccd'] ),
					'loai_hop_dong' => self::gop( $o['loai_hop_dong'] ),
					'tao_luc'       => $o['tao_luc'],
				);
			}
			foreach ( self::tach_don_vi( $o['don_vi'] ) as $dv ) {
				/* Khoá theo cặp mã+đơn vị: cùng một người khai lại cùng một đơn vị thì gộp, còn
				   khai đơn vị khác thì thêm dòng. Đúng nghĩa "làm ở nhiều gian". */
				$k = $ma . "\x1f" . $dv;
				$gan[ $k ] = array(
					'ma_nv'      => $ma,
					'don_vi'     => $dv,
					'tinh_thanh' => $o['tinh_thanh'],
					'tao_luc'    => $o['tao_luc'],
				);
			}
		}
		return array(
			'nguoi'  => array_values( $nguoi ),
			'gan'    => array_values( $gan ),
			'bo_qua' => $bo,
		);
	}

	private static function lay_o( $r ) {
		$o = array();
		foreach ( self::NV_COT as $i => $ten ) {
			$o[ $ten ] = isset( $r[ $i ] ) ? trim( (string) $r[ $i ] ) : '';
		}
		return $o;
	}

	/* ==========================================================================================
	 *  SHEET CƠ SỞ — `CS_<tên cơ sở>`, dạng NGANG
	 *
	 *      hàng ngày   : (A) (B) │ 2026-07-01 · · · · │ 2026-07-02 · · · · │ …
	 *      hàng tiêu đề: Họ và Tên  ID │ Giờ Vào / Ảnh · Giờ Ra / Ảnh · Thời gian │ …
	 *      hàng NV     : Trần Thị…  MNNV2KVC0106 │ … │
	 *
	 *  Mỗi tháng một KHỐI xếp dọc, cách nhau vài hàng trống.
	 * ========================================================================================== */
	const CS_SUB       = 'Giờ Vào / Checkin';
	const CS_COT_DAU   = 2;   // ngày đầu tiên luôn ở cột C (chỉ số 2)
	const CS_MOI_NGAY  = 5;   // vào · ảnh vào · ra · ảnh ra · thời gian

	/**
	 * Tìm mọi khối tháng. Trả [ ['tieu_de'=>i, 'ngay'=>i-1, 'r1'=>i+1, 'r2'=>…], … ]
	 *
	 * 🔴 NHẬN RA KHỐI BẰNG CỘT C, KHÔNG BẰNG CỘT A. Ảnh Sheet thật cho thấy khối đầu có ô cột A
	 *    TRỐNG ở hàng tiêu đề (chỉ cột B có chữ `ID`). Dựa vào cột A là bỏ sót nguyên khối tháng
	 *    đầu — tức mất cả một tháng chấm công mà không có lỗi nào hiện ra.
	 *
	 *    Cũng KHÔNG nhớ vị trí khối ở chỗ khác. Nhớ ở chỗ khác là sớm muộn lệch với tệp thật, và
	 *    lệch thì hỏng im lặng. Chính `Code.gs` đã dặn đúng điều này.
	 */
	public static function tim_khoi( $hang ) {
		$ds = array();
		foreach ( $hang as $i => $r ) {
			if ( isset( $r[ self::CS_COT_DAU ] ) && self::CS_SUB === trim( (string) $r[ self::CS_COT_DAU ] ) ) {
				$ds[] = array( 'tieu_de' => $i, 'ngay' => $i - 1, 'r1' => $i + 1 );
			}
		}
		$n = count( $ds );
		for ( $k = 0; $k < $n; $k++ ) {
			/* Khối cuối chạy tới hết tệp; khối giữa dừng ngay trước hàng NGÀY của khối sau. */
			$ds[ $k ]['r2'] = ( $k + 1 < $n ) ? ( $ds[ $k + 1 ]['ngay'] - 1 ) : ( count( $hang ) - 1 );
		}
		return $ds;
	}

	/**
	 * Đọc một tệp CSV cơ sở -> danh sách lượt chấm công PHẲNG.
	 *
	 * Mỗi phần tử: co_so · ngay · ma_nv · ho_ten · vao (giây|null) · ra (giây|null) · anh_vao · anh_ra
	 *
	 * Bỏ hẳn những ô trống: sheet 31 ngày × 20 người là 620 ô, phần lớn trống. Ghi cả ô trống
	 * xuống bảng là phình dữ liệu vô ích và làm mọi câu truy vấn chậm đi.
	 */
	public static function doc_co_so( $hang, $co_so, &$canh_bao = null ) {
		$ra = array();
		$canh_bao = array();
		$thay     = array();          // ma_nv -> tên, để bắt một người mang hai mã
		foreach ( self::tim_khoi( $hang ) as $khoi ) {
			$hang_ngay = isset( $hang[ $khoi['ngay'] ] ) ? $hang[ $khoi['ngay'] ] : array();
			/* Ngày nằm ở ô ĐẦU của mỗi cụm 5 cột; bốn ô sau trống vì Sheet gộp ô. */
			$cot_ngay = array();
			for ( $c = self::CS_COT_DAU; $c < count( $hang_ngay ); $c += self::CS_MOI_NGAY ) {
				$n = self::ngay( isset( $hang_ngay[ $c ] ) ? $hang_ngay[ $c ] : '' );
				if ( null !== $n ) { $cot_ngay[ $c ] = $n; }
			}
			for ( $i = $khoi['r1']; $i <= $khoi['r2']; $i++ ) {
				if ( ! isset( $hang[ $i ] ) ) { continue; }
				$r     = $hang[ $i ];
				$ten   = isset( $r[0] ) ? trim( (string) $r[0] ) : '';
				$ma    = isset( $r[1] ) ? trim( (string) $r[1] ) : '';
				if ( '' === $ma ) { continue; }          // hàng trống giữa hai khối

				/* 🔴 MÃ NV LÀ SỐ TRẦN (`1`, `15`, `24`) nghĩa là ô ID chưa được cấp mã chuẩn —
				   trong `CS_VP_KHHCM_1` có năm người như vậy. Nạp vẫn nạp, nhưng phải BÁO, vì mã
				   số trần đụng nhau giữa các cơ sở và không nối được với sheet nhân viên. */
				if ( preg_match( '/^\d+$/', $ma ) ) {
					$canh_bao[] = array( 'kieu' => 'ma_so_tran', 'ma_nv' => $ma, 'ho_ten' => $ten );
				}
				/* Cùng một tên mà nhiều mã khác nhau -> công của người đó bị chia ra trong bảng.
				   `CS_VP_KHHCM_1` có Nguyễn Hữu Thọ mang cả mã `2` lẫn mã `15` trong cùng tháng 8.
				   Gom lại rồi báo MỘT LẦN ở cuối, chứ không báo ngay tại chỗ: báo tại chỗ thì mỗi
				   lần đổi mã lại đẻ một dòng, và cùng một cặp mã hiện hai lần theo hai chiều. */
				$k_ten = self::gop( $ten );
				if ( '' !== $k_ten ) { $thay[ $k_ten ][ $ma ] = $ten; }
				foreach ( $cot_ngay as $c => $ngay ) {
					$o_vao = isset( $r[ $c ] ) ? $r[ $c ] : '';
					$o_ra  = isset( $r[ $c + 2 ] ) ? $r[ $c + 2 ] : '';
					$av    = isset( $r[ $c + 1 ] ) ? trim( (string) $r[ $c + 1 ] ) : '';
					$ar    = isset( $r[ $c + 3 ] ) ? trim( (string) $r[ $c + 3 ] ) : '';

					/* 🔴 GOM MỌI MỐC GIỜ CỦA NGÀY RỒI LẤY SỚM NHẤT / MUỘN NHẤT, thay vì đọc cứng
					   một ô một giá trị. Đúng bằng định nghĩa của hai ô đó, và nhờ vậy ô bẩn kiểu
					   `08:30 13:23:38` vẫn ra đủ hai mốc thay vì mất trắng cả buổi làm. Ô sạch thì
					   kết quả y hệt cách cũ — một mốc ở ô vào, một mốc ở ô ra. */
					$moc = array_merge( self::moc_gio( $o_vao ), self::moc_gio( $o_ra ) );
					foreach ( array( $o_vao, $o_ra ) as $o ) {
						if ( self::o_hong( $o ) ) {
							$canh_bao[] = array( 'kieu' => 'gio_la', 'ngay' => $ngay,
								'ma_nv' => $ma, 'ho_ten' => $ten, 'o' => trim( (string) $o ) );
						} elseif ( count( self::moc_gio( $o ) ) > 1 ) {
							/* Đọc được rồi, nhưng vẫn BÁO: ô đáng lẽ một mốc mà có hai là ô ai đó
							   sửa tay trong Sheet. Máy tự xử được lần này, còn người thì nên biết
							   để sửa gốc — im lặng là lần sau nó thành ba mốc và không ai hay. */
							$canh_bao[] = array( 'kieu' => 'o_nhieu_moc', 'ngay' => $ngay,
								'ma_nv' => $ma, 'ho_ten' => $ten, 'o' => trim( (string) $o ) );
						}
					}
					sort( $moc );
					$vao = $moc ? $moc[0] : null;
					$ra_ = ( count( $moc ) > 1 ) ? $moc[ count( $moc ) - 1 ] : null;
					if ( null === $vao && null === $ra_ && '' === $av && '' === $ar ) { continue; }
					$ra[] = array(
						'co_so'   => $co_so,
						'ngay'    => $ngay,
						'ma_nv'   => $ma,
						'ho_ten'  => $ten,
						'vao'     => $vao,
						'ra'      => $ra_,
						'anh_vao' => $av,
						'anh_ra'  => $ar,
					);
				}
			}
		}
		foreach ( $thay as $ds ) {
			if ( count( $ds ) < 2 ) { continue; }
			$ma_ds = array_keys( $ds );
			sort( $ma_ds );
			$canh_bao[] = array(
				'kieu'   => 'mot_nguoi_nhieu_ma',
				'ho_ten' => reset( $ds ),
				'ma_nv'  => implode( ' / ', $ma_ds ),
			);
		}
		/* Gộp cảnh báo trùng: cùng một người xuất hiện ở hai khối tháng thì chỉ báo một lần.
		   Danh sách cảnh báo dài gấp đôi vì lặp là người ta thôi đọc nó — mà đọc được thì mới có
		   tác dụng. */
		$da = array();
		$loc = array();
		foreach ( $canh_bao as $c ) {
			$k = implode( "\x1f", $c );
			if ( isset( $da[ $k ] ) ) { continue; }
			$da[ $k ] = 1;
			$loc[]    = $c;
		}
		$canh_bao = $loc;
		return $ra;
	}

	/**
	 * Tên cơ sở lấy từ tên tệp Google Sheets xuất ra.
	 *
	 *     ( Đang chạy ) Hệ Thống Chấm Công Cơ Sở - CS_VP_KH-HCM.csv  ->  VP_KH-HCM
	 *     …Cơ Sở - CS_TUTU_TP.csv                                   ->  TUTU_TP
	 *     …Cơ Sở - CS_VP_KH-HCM (1).csv                             ->  VP_KH-HCM
	 *
	 * 🔴 VÌ SAO PHẢI VIẾT LẠI: bản đầu khớp `[A-Za-z0-9_]+`, tức KHÔNG nhận dấu gạch ngang. Tệp
	 *    thật của anh Thắng tên là `CS_VP_KH-HCM.csv` nên hàm trả về rỗng, và màn nạp chặn ngay
	 *    ở cửa bằng câu "Không đoán được cơ sở từ tên tệp" — đúng chỗ tắc anh Thắng gặp. Một dấu
	 *    gạch ngang trong tên cơ sở là chuyện bình thường; chính bộ đọc mới là chỗ hẹp.
	 *
	 * Cách làm: cắt đuôi `.csv`, bỏ hậu tố bản sao của trình duyệt (` (1)`), rồi lấy TẤT CẢ phần
	 * sau `CS_` cuối cùng — thay vì liệt kê trước những ký tự nào được phép. Liệt kê là kiểu nào
	 * cũng sót, và sót thì tắc y như lần này.
	 *
	 * Vẫn giữ một cửa chặn: nếu phần lấy ra có ký tự lạ (dấu tiếng Việt, dấu chấm than, dấu /)
	 * thì trả rỗng để màn nạp bắt người ta tự chọn cơ sở, chứ không đẻ ra một mã cơ sở kỳ quặc
	 * rồi ghi thẳng xuống bảng — mã cơ sở là khoá, đặt sai một lần là dữ liệu nằm sai chỗ mãi.
	 */
	/**
	 * Chuẩn hoá mã cơ sở người ta tự gõ vào ô, cho khớp đúng khuôn mã lấy từ tên tệp.
	 *
	 * Phải đi qua CÙNG MỘT khuôn với `co_so_tu_ten`, nếu không thì gõ tay ra `VP KH-HCM` còn tên
	 * tệp ra `VP_KH-HCM`, và đó là HAI cơ sở khác nhau trong bảng — cùng một chỗ mà công nằm
	 * hai nơi. Trả rỗng nếu gõ ký tự không dùng làm mã được.
	 */
	/**
	 * Chuẩn hoá mã cơ sở — dùng chung cho cả tên tệp lẫn ô người ta tự gõ.
	 *
	 * 🔴 KHÔNG liệt kê trước "ký tự nào được phép" nữa. Đã sai hai lần rồi:
	 *      lần 1: `CS_VP_KH-HCM.csv`            — thiếu dấu gạch ngang
	 *      lần 2: `CS_(PART TIME )_POSH+JP.csv` — thiếu ngoặc, khoảng trắng, dấu cộng
	 *    Liệt kê thì lần nào cũng sót một ký tự mới, và sót là màn nạp chặn ngay ở cửa.
	 *
	 * Giờ đi theo hướng ngược: DỌN chuỗi về khuôn dùng được, chỉ từ chối khi dọn xong vẫn còn
	 * thứ không đặt làm mã được (dấu tiếng Việt, gạch chéo, ký tự điều khiển).
	 *
	 *    `(PART TIME )_POSH+JP`  ->  `PART_TIME_POSH+JP`
	 *    ` VP KH-HCM `           ->  `VP_KH-HCM`
	 *
	 * Cả hai đường đi qua ĐÚNG hàm này, nên gõ tay và lấy từ tên tệp luôn ra cùng một mã. Hai
	 * khuôn khác nhau là cùng một cơ sở có công nằm hai chỗ.
	 */
	public static function chuan_co_so( $s ) {
		$s = trim( (string) $s );
		if ( '' === $s ) { return ''; }
		/* Dấu nhóm trong tên tệp chỉ là chú thích của người đặt tên, không phải phần của mã. */
		$s = str_replace( array( '(', ')', '[', ']', '{', '}' ), ' ', $s );
		/* Khoảng trắng -> gạch dưới, rồi gộp gạch dưới liên tiếp và cắt hai đầu. Không gộp thì
		   `(PART TIME )_POSH` ra `PART_TIME__POSH` — nhìn thì giống, mà là mã khác. */
		$s = preg_replace( '/\s+/u', '_', $s );
		$s = preg_replace( '/_{2,}/', '_', $s );
		$s = trim( $s, '_' );
		if ( '' === $s ) { return ''; }
		/* Dọn xong mà vẫn còn ký tự lạ thì TỪ CHỐI, để người ta tự gõ. Mã cơ sở là khoá trong
		   bảng — đẻ ra một mã kỳ quặc là dữ liệu nằm sai chỗ vĩnh viễn, sửa lại rất tốn. */
		if ( ! preg_match( '/^[A-Za-z0-9_.+&\-]+$/', $s ) ) { return ''; }
		return $s;
	}

	/**
	 * Tên cơ sở lấy từ tên tệp Google Sheets xuất ra.
	 *
	 *     ( Đang chạy ) Hệ Thống Chấm Công Cơ Sở - CS_VP_KH-HCM.csv        ->  VP_KH-HCM
	 *     ( Đang chạy ) Hệ Thống Chấm Công Cơ Sở - CS_(PART TIME )_POSH+JP.csv -> PART_TIME_POSH+JP
	 *     …Cơ Sở - CS_VP_KH-HCM (1).csv                                    ->  VP_KH-HCM
	 *
	 * Lấy TẤT CẢ phần sau `CS_` cuối cùng rồi đưa qua chuan_co_so() — một khuôn duy nhất.
	 */
	public static function co_so_tu_ten( $ten ) {
		$ten = basename( str_replace( '\\', '/', (string) $ten ) );
		$ten = preg_replace( '/\.csv$/i', '', $ten );
		/* Hậu tố bản sao của trình duyệt: " (1)". Cắt TRƯỚC khi bỏ dấu ngoặc, không thì nó thành
		   "_1" dính vào mã và tệp tải lần hai ra một cơ sở khác. */
		$ten = preg_replace( '/\s*\(\d+\)$/', '', $ten );

		$vt = strripos( $ten, 'CS_' );
		if ( false === $vt ) { return ''; }
		return self::chuan_co_so( substr( $ten, $vt + 3 ) );
	}
}
