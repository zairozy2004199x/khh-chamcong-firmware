<?php
/**
 * SINH MÃ BẢN GHI — `RP20260921-0007`. Thay `jpNextId_()` của `JP2_01_Core.gs`.
 *
 * =============================================================================================
 * 🔴 SINH MÃ VÀ GHI DÒNG PHẢI ĐI LIỀN MỘT NHỊP.
 * =============================================================================================
 * Bản gốc tách đôi: `jpNextId_()` lấy số từ Script Properties dưới một khoá script, rồi nơi gọi
 * mới ghi dòng. Giữa hai nước đi ấy có một khe hở — và bên Apps Script phải mượn `LockService`
 * chờ tới 20 giây để bịt.
 *
 * Bên này không đi lối ấy. Mã sinh ra rồi GHI NGAY, và nếu đâm vào khoá chính (ai đó vừa lấy
 * đúng số ấy) thì lùi lại lấy số kế tiếp rồi thử lại. Chốt chặn là KHOÁ CHÍNH của chính bảng
 * đích — thứ luôn đúng, không cần ai nhớ khoá hộ.
 *
 * ⚠️ VÌ SAO KHÔNG DỰNG MỘT BẢNG ĐẾM RIÊNG (ý ban đầu, đã bỏ):
 *    Bảng đếm chỉ đúng khi lượt tăng số là NGUYÊN TỬ. Câu `UPDATE n = n + 1` rồi `SELECT n` thì
 *    không: giữa hai câu ấy, một lượt khác chen vào là hai người cầm cùng một số. Muốn nguyên
 *    tử thật phải dùng cú pháp riêng của MySQL (`LAST_INSERT_ID(n+1)`), mà SQLite của bệ đỡ thử
 *    không hiểu — tức là ĐƯỜNG CHẠY THẬT KHÔNG CÓ BÀI KIỂM NÀO ĐI QUA. Một chốt chặn không ai
 *    thử được thì không phải chốt chặn.
 *    Cách dưới đây chạy y nhau trên cả hai, nên bài kiểm đi đúng đường thật.
 *
 * ⚠️ SỐ ĐẾM THEO NGÀY, không phải theo đời dự án như bản gốc. Mã vẫn duy nhất vì đã mang sẵn
 *    ngày. Dữ liệu cũ chuyển sang giữ nguyên mã cũ, và số mới sinh trong ngày ấy nối tiếp đúng
 *    số lớn nhất đang có — nên không có chuyện đụng mã với dữ liệu đã chuyển.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Ma {

	/** Thử lại tối đa bao nhiêu lượt khi đâm vào mã đã có. */
	const SO_LUOT = 20;

	/**
	 * Dựng mã từ tiền tố, ngày và số thứ tự. `RP` + `20260921` + `-0007`.
	 *
	 * 🔴 ĐỆM CHO ĐỦ 4 CHỮ SỐ, NHƯNG KHÔNG CẮT KHI VƯỢT — ĐÂY LÀ CHỖ CỐ Ý KHÁC BẢN GỐC.
	 *    Bản gốc viết `('0000' + n).slice(-4)`, tức số 12345 ra `-2345`: cắt mất chữ số đầu và
	 *    đẻ ra một mã TRÙNG với số 2345. Mà số đếm bên ấy chạy suốt đời dự án không reset, nên
	 *    tới bản ghi thứ 10.000 của một tiền tố là bắt đầu vòng lại.
	 *    Bên này số đếm theo NGÀY nên gần như không chạm tới, song cắt cụt thì vẫn là sinh mã
	 *    trùng — một lỗi không bao giờ nên chép. Để nó dài thêm một chữ số thì xấu hơn tí, mà
	 *    không mất bản ghi nào.
	 */
	public static function tao( $tien_to, $n, $ngay = '' ) {
		if ( '' === $ngay ) { $ngay = self::hom_nay(); }
		return $tien_to . str_replace( '-', '', $ngay ) . '-'
			. str_pad( (string) (int) $n, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Hôm nay theo giờ Việt Nam.
	 *
	 * 🔴 GHIM MÚI GIỜ, đừng lấy giờ máy chủ. Bản gốc ghim `Asia/Ho_Chi_Minh`; máy chủ hosting
	 *    đặt sai múi là mọi mã sinh sau 17h mang ngày hôm sau, và đối soát theo ngày lệch hẳn
	 *    một hàng.
	 */
	public static function hom_nay( $luc = null ) {
		/* `$luc` chỉ để BÀI KIỂM chọn đúng một khoảnh khắc. Không có cách ghim thời điểm thì
		   phép kiểm múi giờ chỉ đúng vào những giờ mà Việt Nam và UTC tình cờ cùng ngày — tức
		   là xanh 17/24 giờ mỗi ngày dù mã lấy sai múi. */
		$d = ( $luc instanceof DateTimeInterface )
			? DateTime::createFromFormat( 'U', $luc->getTimestamp() )
			: new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$d->setTimezone( new DateTimeZone( 'Asia/Ho_Chi_Minh' ) );
		return $d->format( 'Y-m-d' );
	}

	/**
	 * Số thứ tự kế tiếp cho một tiền tố trong ngày, đọc từ CHÍNH bảng đích.
	 *
	 * ⚠️ Đọc từ bảng đích chứ không từ một sổ đếm riêng: sổ đếm có thể lệch khỏi thực tế (ai đó
	 *    chuyển dữ liệu vào tay, hoặc khôi phục bản sao lưu), còn bảng đích thì luôn là sự thật.
	 */
	public static function so_ke_tiep( $tab, $tien_to, $ngay = '' ) {
		if ( '' === VHJP_Nguon::khoa( $tab ) ) { return 0; }
		if ( '' === $ngay ) { $ngay = self::hom_nay(); }
		$dau = $tien_to . str_replace( '-', '', $ngay ) . '-';
		$ma  = VHJP_Nguon::ma_lon_nhat( $tab, $dau );
		if ( '' === $ma ) { return 1; }
		return ( (int) substr( $ma, strlen( $dau ) ) ) + 1;
	}

	/**
	 * Sinh mã rồi GHI dòng ngay. Trả dòng đã ghi (kèm mã), hoặc `false`.
	 *
	 * Đâm vào mã đã có thì lùi sang số kế tiếp và thử lại — đó là chỗ duy nhất chặn hai lượt
	 * cùng lúc lấy chung một mã, và nó chặn được vì khoá chính của bảng đích nói KHÔNG.
	 *
	 * ⚠️ PHÂN BIỆT "ĐÂM VÀO MÃ ĐÃ CÓ" VỚI "CƠ SỞ DỮ LIỆU HỎNG". Hai thứ cùng trả `false` từ
	 *    `$wpdb->insert()`. Thử lại cho ca đầu là đúng; thử lại cho ca sau là quay 20 vòng vô
	 *    ích rồi vẫn hỏng, mà nhật ký thì đầy. Nên hỏi `last_error` để biết mình đang gặp cái
	 *    nào.
	 */
	public static function them( $tab, $tien_to, $obj ) {
		$k = VHJP_Nguon::khoa( $tab );
		if ( '' === $k ) { return false; }
		$n = self::so_ke_tiep( $tab, $tien_to );

		for ( $i = 0; $i < self::SO_LUOT; $i++ ) {
			$hang = $obj;
			$hang[ $k ] = self::tao( $tien_to, $n + $i );
			$kq = VHJP_Nguon::them( $tab, $hang );
			if ( false !== $kq ) { return $kq; }
			if ( ! self::la_trung( VHJP_Nguon::loi_cuoi() ) ) { return false; }
		}
		return false;
	}

	/**
	 * Câu lỗi này có phải "mã đã có" không.
	 *
	 * MySQL nói `Duplicate entry`; SQLite của bệ đỡ thử nói `UNIQUE constraint failed`. Nhận cả
	 * hai, vì bài kiểm phải đi được đúng đường mà thật sự chạy.
	 */
	public static function la_trung( $loi ) {
		$s = strtolower( (string) $loi );
		return false !== strpos( $s, 'duplicate entry' )
			|| false !== strpos( $s, 'unique constraint failed' )
			|| false !== strpos( $s, 'duplicate key' );
	}
}
