<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * TRỤC PHÂN TÍCH — KHUNG CHUNG CHO MỌI CÁCH "CẮT NGANG" MỘT DÒNG CHI
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 *
 * Anh Thắng 22/09/2026, sau khi em khảo mã nguồn mở: *"Em làm thử anh xem"*.
 *
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * 🔴 VẤN ĐỀ NÓ GIẢI: MỖI TRỤC MỚI LÀ MỘT LƯỢT SỬA SÁU CHỖ
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * Thêm "Setup / Vận hành" tuần này phải đụng đúng sáu nơi: cột trong sơ đồ bảng · hàm chuẩn hoá
 * · hàm đọc · chỗ ghi dòng · chỗ đọc dòng lên màn · gói khởi động — cộng ô nhập và lượt dọn form
 * bên giao diện. Bỏ sót một chỗ là hỏng im lặng, và tuần này đã cắn đúng thế ba lần (mất đơn ×2,
 * mất dòng chi ×1).
 *
 * Trục thứ hai sẽ đến (theo dự án? theo nguồn tiền?), và nó sẽ phải đi lại đúng sáu chỗ ấy.
 *
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * 🔴 VÌ SAO LÀ MỘT TRỤC RIÊNG, KHÔNG PHẢI MỘT NHÁNH CỦA DANH MỤC LOẠI CHI PHÍ
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * Đây không phải ý riêng của bộ này — hai hệ ERP nguồn mở lớn nhất đều tách y hệt:
 *
 *   · ERPNext gọi là **Accounting Dimension**, và nói thẳng rằng hệ thống tài khoản (Chart of
 *     Accounts) dựng để nộp cho thuế; muốn biết mảng nào có lãi thì phải có một CÂY RIÊNG —
 *     Chart of Cost Centers. Trục phụ "thêm axis mà KHÔNG đụng vào cây tài khoản".
 *   · Odoo gọi là **Analytic accounting**, cũng là một trục song song với sổ cái.
 *
 * Đối chiếu sang bộ này:
 *     Hệ thống tài khoản (641/642…)  ->  cột `tk_no` / `tk_co`
 *     Cost Center                    ->  Cơ sở · Mảng kinh doanh
 *     Accounting Dimension           ->  **CHÍNH LÀ ĐÂY**: Khối (miền) · Setup/Vận hành · …
 *
 * Nhét trục vào cây danh mục là nhân đôi mọi loại chi phí ("Điện nước - setup", "Điện nước -
 * vận hành"…), mà vẫn không trả lời được khi một dòng rơi vào cả hai. Nó hỏng ở chỗ đắt nhất:
 * danh mục là thứ kế toán phải dò bằng mắt mỗi lần nhập.
 *
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * ⚠️ MỘT TRỤC = MỘT CỘT TRONG SỔ, KHÔNG PHẢI MỘT BẢNG RIÊNG
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * Dựng một bảng `chiphi_truc(dong_id, truc, gia_tri)` thì thêm trục khỏi đụng sơ đồ bảng —
 * nghe gọn hơn hẳn. Nhưng mọi câu gom báo cáo đều phải nối thêm một bảng cho MỖI trục, và câu
 * "setup tốn bao nhiêu ở miền Bắc" hoá thành hai lượt nối lồng nhau trên bảng dòng chi lớn nhất
 * kho. Cột thì `WHERE giai_doan='Setup' AND khoi='mb'` là xong.
 *
 * Giá phải trả: thêm trục vẫn phải thêm một cột và NÂNG `SCHEMA_VERSION`. Đó là chỗ DUY NHẤT
 * còn phải sửa tay — năm chỗ còn lại nay đi qua khung này. Và nó là chỗ khó quên nhất, vì quên
 * thì `install()` không chạy và cột không có (đúng vụ mất dòng chi 22/09/2026).
 *
 * ⚠️ RỖNG LÀ MỘT GIÁ TRỊ THẬT, KHÔNG PHẢI "CHƯA XÁC ĐỊNH". Mỗi trục khai `macDinh`; dòng cũ
 *    mang rỗng thì ĐỌC ra giá trị ấy. Xem `chuan()` và `doc()` — hai việc khác nhau, đừng gộp.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Truc {

	/**
	 * BẢNG KHAI TRỤC — nguồn sự thật duy nhất.
	 *
	 * Mỗi mục:
	 *   cot      cột trong bảng `chiphi`. Thêm trục = thêm cột + nâng `VHCP_DB::SCHEMA_VERSION`.
	 *   khoa     tên khoá mà giao diện gửi lên / nhận về.
	 *   nhan     nhãn trên form nhập.
	 *   kieu     'tich' = một ô tích hai giá trị · 'chon' = ô chọn nhiều giá trị.
	 *   gtri     danh sách giá trị hợp lệ.
	 *   macDinh  giá trị khi ô rỗng (dòng cũ) hoặc khi không tích.
	 *   bat      chỉ với kiểu 'tich': tích vào = giá trị này.
	 *   nhanBat  chỉ với kiểu 'tich': chữ bên cạnh ô tích.
	 *   phu      dòng nhắc nhỏ dưới ô.
	 *
	 * 🔴 KHÔNG CHÉP LẠI CHUỖI GIÁ TRỊ Ở ĐÂY. Hai hằng `GIAI_DOAN_*` đã sống ở `VHCP_Don` và cả
	 *    bài kiểm lẫn mã cũ đều đọc chúng; gõ lại "Setup" ở đây là hai nơi khai một sự thật, và
	 *    ngày nào một bên sửa thì `chuan()` lẳng lặng ngã mọi dòng về rỗng.
	 */
	public static function ds() {
		$ds = array(
			'giaiDoan' => array(
				'cot'     => 'giai_doan',
				'khoa'    => 'giaiDoan',
				'nhan'    => 'Giai đoạn',
				'kieu'    => 'tich',
				'gtri'    => VHCP_Don::GIAI_DOAN_DS,
				'macDinh' => VHCP_Don::GIAI_DOAN_VH,
				'bat'     => VHCP_Don::GIAI_DOAN_SETUP,
				'nhanBat' => '🛠 Chi phí <b>setup ban đầu</b>',
				'phu'     => 'Không tích = chi phí <b>vận hành</b> (mặc định)',
			),
		);
		/**
		 * ĐƯỜNG MỞ RỘNG — bộ khác góp thêm một trục mà không phải sửa tệp này.
		 *
		 * ⚠️ GÓP TRỤC KHÔNG PHẢI GÓP QUYỀN. Giá trị lạ vẫn bị `chuan()` ngã về rỗng, và cột
		 *    phải có thật trong sơ đồ bảng thì lượt ghi mới nhận — một trục bịa ra ở đây không
		 *    tự mở được đường ghi nào.
		 */
		return apply_filters( 'vhcp_truc_ds', $ds );
	}

	/** Một trục theo khoá, hoặc `null`. */
	public static function mot( $ma ) {
		$ds = self::ds();
		return isset( $ds[ $ma ] ) ? $ds[ $ma ] : null;
	}

	/**
	 * GHI — màn gửi lên cái gì thì ghi cái ấy, giá trị lạ về rỗng.
	 *
	 * 🔴 RỖNG VẪN GHI RỖNG. Một lượt gọi chỉ sửa ô khác mà không khai trục này thì nó gửi rỗng;
	 *    lấp giá trị mặc định vào đó là tự đóng dấu lên dữ liệu người ta không hề động tới.
	 *    Nghĩa của ô rỗng nằm ở `doc()`, không nằm ở đây.
	 */
	public static function chuan( $ma, $v ) {
		$tr = self::mot( $ma );
		if ( ! $tr ) { return ''; }
		$t = trim( (string) $v );
		if ( '' === $t ) { return ''; }
		foreach ( (array) $tr['gtri'] as $x ) {
			if ( mb_strtolower( $x ) === mb_strtolower( $t ) ) { return $x; }
		}
		return '';
	}

	/**
	 * ĐỌC — dòng trong sổ đọc ra là gì. **RỖNG = GIÁ TRỊ MẶC ĐỊNH CỦA TRỤC.**
	 *
	 * 🔴 ĐỔI Ở CHỖ ĐỌC, KHÔNG ĐI GHI ĐÈ SỔ. Một lượt `UPDATE` quét cả bảng để lấp mặc định vào
	 *    mấy trăm dòng cũ là thứ không lùi lại được, mà cũng chẳng cần: nghĩa của ô rỗng là một
	 *    LUẬT ĐỌC, và luật thì sửa ở một chỗ.
	 */
	public static function doc( $ma, $v ) {
		$tr = self::mot( $ma );
		if ( ! $tr ) { return ''; }
		$t = self::chuan( $ma, $v );
		return ( '' === $t ) ? (string) $tr['macDinh'] : $t;
	}

	/**
	 * Đọc MỌI trục của một dòng trong sổ -> mảng {khoá: giá trị} để gửi xuống màn.
	 *
	 * ⚠️ Cột chưa có trong sổ (bản cũ chưa nâng sơ đồ bảng) thì coi như rỗng, và `doc()` trả về
	 *    mặc định — KHÔNG ném lỗi. Một trục mới không được làm chết đường đọc đơn.
	 */
	public static function doc_dong( $row ) {
		$row = (array) $row;
		$ra  = array();
		foreach ( self::ds() as $ma => $tr ) {
			$c = $tr['cot'];
			$ra[ $tr['khoa'] ] = self::doc( $ma, isset( $row[ $c ] ) ? $row[ $c ] : '' );
		}
		return $ra;
	}

	/**
	 * Dựng phần CỘT của một dòng sắp ghi, từ dữ liệu giao diện gửi lên.
	 *
	 * @param callable $get Hàm lấy một khoá trong gói giao diện gửi lên.
	 */
	public static function ghi_dong( $get ) {
		$ra = array();
		foreach ( self::ds() as $ma => $tr ) {
			$ra[ $tr['cot'] ] = self::chuan( $ma, call_user_func( $get, $tr['khoa'] ) );
		}
		return $ra;
	}

	/** Bản rút gọn cho gói khởi động — màn dựng ô nhập từ đây, không gõ cứng trục nào. */
	public static function boot() {
		$ra = array();
		foreach ( self::ds() as $ma => $tr ) {
			$ra[] = array(
				'ma'      => $ma,
				'khoa'    => $tr['khoa'],
				'nhan'    => $tr['nhan'],
				'kieu'    => $tr['kieu'],
				'gtri'    => array_values( (array) $tr['gtri'] ),
				'macDinh' => $tr['macDinh'],
				'bat'     => isset( $tr['bat'] ) ? $tr['bat'] : '',
				'nhanBat' => isset( $tr['nhanBat'] ) ? $tr['nhanBat'] : '',
				'phu'     => isset( $tr['phu'] ) ? $tr['phu'] : '',
			);
		}
		return $ra;
	}
}
