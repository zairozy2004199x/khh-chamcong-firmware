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
			if ( '' !== $o['don_vi'] ) {
				/* Khoá theo cặp mã+đơn vị: cùng một người khai lại cùng một đơn vị thì gộp, còn
				   khai đơn vị khác thì thêm dòng. Đúng nghĩa "làm ở nhiều gian". */
				$k = $ma . "\x1f" . $o['don_vi'];
				$gan[ $k ] = array(
					'ma_nv'      => $ma,
					'don_vi'     => $o['don_vi'],
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
	public static function doc_co_so( $hang, $co_so ) {
		$ra = array();
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
				foreach ( $cot_ngay as $c => $ngay ) {
					$vao = self::giay( isset( $r[ $c ] ) ? $r[ $c ] : '' );
					$ra_ = self::giay( isset( $r[ $c + 2 ] ) ? $r[ $c + 2 ] : '' );
					$av  = isset( $r[ $c + 1 ] ) ? trim( (string) $r[ $c + 1 ] ) : '';
					$ar  = isset( $r[ $c + 3 ] ) ? trim( (string) $r[ $c + 3 ] ) : '';
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
		return $ra;
	}

	/** Tên cơ sở từ tên tệp: `…CS_TUTU_TP.csv` -> `TUTU_TP`. */
	public static function co_so_tu_ten( $ten ) {
		$ten = basename( (string) $ten );
		if ( preg_match( '/CS_([A-Za-z0-9_]+)\.csv$/i', $ten, $m ) ) { return $m[1]; }
		return '';
	}
}
