<?php
/**
 * CHI PHÍ KỸ THUẬT — dự án Tháo dỡ / Setup lắp đặt + sheet "Chi phí cơ sở" chung xuyên suốt.
 *
 * App cũ mỗi dự án là 1 tab Google Sheet; ở đây là các dòng trong vhcpvp_da_line khóa theo
 * (ma_da, row_no). row_no vẫn bắt đầu từ 5 để giao diện gọi updateDuAnLine(maDA, row, rec) như cũ.
 *
 * Quy ước tính tiền giữ nguyên:
 *   - Hạng mục lớn (cap_cha rỗng) mang DỰ TOÁN. Thực tế của nó chỉ tính khi KHÔNG có mục con.
 *   - Mục con / (Phát sinh) chỉ mang THỰC TẾ; hình thức chi thừa hưởng của hạng mục cha.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPVP_DuAn {

	const DATA_ROW = 5;

	public static function find( $ma_da ) {
		global $wpdb;
		$t = VHCPVP_DB::t( 'da_index' );
		return VHCPVP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s", (string) $ma_da ) );
	}

	/**
	 * ĐƠN VỊ của một dự án — neo theo NGƯỜI TẠO, không theo cơ sở.
	 *
	 * 🔴 KHÁC HAI MẢNG KIA, VÀ ĐÂY LÀ LÝ DO. Bảng `da_index` KHÔNG có cột cơ sở: một dự án
	 *    (setup/tháo dỡ) trải qua nhiều gian, cơ sở chỉ nằm trên từng DÒNG (`da_line.gian`),
	 *    và các dòng ấy có thể thuộc nhiều gian khác nhau. Neo theo cơ sở thì phải trả lời
	 *    "dự án chạm gian của cả hai bên thì của ai" — không có câu trả lời đúng, và câu nào
	 *    cũng làm lộ số của một bên.
	 *
	 *    Người lập thì luôn có đúng một nhà. Dự án là việc của một bên đứng ra làm, nên neo
	 *    vào nhà của người ấy là đúng nghiệp vụ và không có ca nhập nhằng.
	 *
	 * ⚠️ Người tạo rỗng (dữ liệu cũ, nhập từ sổ) -> `cua_nguoi()` trả nhà mặc định = K&H.
	 *    Đúng: trước khi có POSH thì mọi dự án đều là dự án K&H.
	 */
	public static function don_vi_cua( $khoa, $kieu = 'ma_da' ) {
		if ( 'ma_da' !== $kieu ) { return null; }
		$r = self::find( $khoa );
		if ( ! $r ) { return null; }
		return VHCPVP_DonVi::cua_nguoi( isset( $r['nguoi_tao'] ) ? $r['nguoi_tao'] : '' );
	}

	private static function lines_of( $ma_da ) {
		global $wpdb;
		$t = VHCPVP_DB::t( 'da_line' );
		return VHCPVP_DB::rows( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s ORDER BY row_no ASC", (string) $ma_da ) );
	}

	private static function next_row( $ma_da ) {
		global $wpdb;
		$t   = VHCPVP_DB::t( 'da_line' );
		$max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(row_no) FROM $t WHERE ma_da=%s", (string) $ma_da ) );
		return max( self::DATA_ROW, $max + 1 );
	}

	/** Dòng "có nội dung": bỏ hàng trống hoàn toàn (giống điều kiện lọc của app cũ). */
	private static function is_real( $r ) {
		return ! ( trim( (string) $r['noi_dung'] ) === '' && ! ( VHCPVP_Util::num( $r['du_toan'] ) || VHCPVP_Util::num( $r['thuc_te'] ) ) );
	}

	// ---------------------------------------------------------------- tạo / danh sách

	public static function create_du_an( $loai, $ten, $nguoi, $tu = '', $den = '' ) {
		global $wpdb;
		$loai = trim( (string) $loai );
		if ( $loai === 'Chi phí cơ sở' ) {
			/* Tên rỗng = mở lại sổ CHUNG cũ (nút "🔧 Mở sổ chung (cũ)" vẫn gọi đúng như trước).
			   Có tên = lập MỘT ĐƠN chi phí cơ sở cho một TUẦN — xem chốt ở khối "MỘT ĐỢT LÀ
			   MỘT ĐƠN" bên dưới. */
			$ten_cs = VHCPVP_Util::san( $ten );
			return ( '' === $ten_cs ) ? self::ensure_co_so_chung( $nguoi ) : self::tao_don_coso( $ten_cs, $nguoi, $tu, $den );
		}
		$ten = VHCPVP_Util::san( $ten );
		if ( ! in_array( $loai, self::LOAI_DU_AN, true ) ) {
			return VHCPVP_Util::err( 'Loại dự án không hợp lệ: "' . $loai . '". Hợp lệ: '
				. implode( ' · ', self::LOAI_DU_AN ) . '.' );
		}
		if ( $ten === '' ) { return VHCPVP_Util::err( 'Nhập tên dự án' ); }
		$ma = VHCPVP_Util::uid( 'DA' );
		$wpdb->insert( VHCPVP_DB::t( 'da_index' ), array(
			'ma_da'      => $ma,
			'ten'        => $ten,
			'loai'       => $loai,
			'trang_thai' => 'Đang làm',
			'ngay_tao'   => VHCPVP_Util::now_sql(),
			'nguoi_tao'  => (string) $nguoi,
		) );
		return VHCPVP_Util::ok( array( 'maDA' => $ma, 'ten' => $ten, 'loai' => $loai, 'sheet' => '', 'trangThai' => 'Đang làm', 'url' => '' ) );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * CHI PHÍ CƠ SỞ KỸ THUẬT: MỘT ĐỢT LÀ MỘT ĐƠN, MỘT ĐƠN NHIỀU CƠ SỞ.
	 *
	 * Anh Thắng 11/09/2026: *"tiếp tới chi phí kỹ thuật cơ sở, sẽ giống kiểu chi phí bên POSH,
	 * 1 đơn nhiều cơ sở chung 1 đơn"*.
	 *
	 * 🔴 CÁI CŨ KHÔNG MẤT. Từ đầu chi phí cơ sở kỹ thuật là MỘT "sheet" chung xuyên suốt: mọi
	 *    gian, mọi tháng, mọi khoản dồn vào một chỗ. Chỗ ấy không bao giờ chốt được, nên cũng
	 *    không bao giờ xin tạm ứng hay quyết toán theo đơn được — đúng thứ anh Thắng vừa dựng
	 *    xong cho dự án Setup/Tháo dỡ. Bản này KHÔNG xoá nó (dữ liệu đã nằm đó, và các dòng ấy
	 *    đang mang mã tài khoản đã xuất MISA), mà cho lập THÊM từng đơn theo đợt bên cạnh.
	 *
	 * 🔴 PHÂN BIỆT BẰNG MỘT VẾT GHIM, KHÔNG BẰNG LOẠI. Cả hai cùng loại 'Chi phí cơ sở': loại
	 *    là thứ `loai_cp_mac_dinh()` tra ra mã tài khoản và `exportMisaKyThuat()` lọc theo, đổi
	 *    loại của đơn mới là đổi luôn cách hạch toán của nó. Đơn CHUNG là đơn được ghim ở khoá
	 *    `da_coso_chung`; ghim trỏ đúng thứ `ensure_co_so_chung()` vẫn chọn từ trước — dự án
	 *    loại 'Chi phí cơ sở' CŨ NHẤT — nên sổ đang chạy không đổi hành vi nào.
	 *
	 * ⚠️ CHƯA CÓ ĐƠN CHUNG THÌ GHIM VẾT '-'. Không có vết ấy thì đơn theo đợt đầu tiên lại
	 *    chính là dự án 'Chi phí cơ sở' cũ nhất, và lần tra sau sẽ ghim NHẦM nó làm đơn chung —
	 *    đơn của nhân viên bỗng không đóng, không xoá, không đổi tên được, không báo vì sao.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */
	const MK_COSO_CHUNG = 'da_coso_chung';

	/** Tên loại của đơn chi phí cơ sở — thứ phân biệt "đơn" với "dự án" trên mọi màn. */
	const LOAI_COSO = 'Chi phí cơ sở';

	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * LOẠI DỰ ÁN HỢP LỆ — KHAI Ở MỘT CHỖ.
	 *
	 * Anh Thắng 12/09/2026, nhìn ô "LOẠI DỰ ÁN" của nhân viên Marketing vẫn xổ ra Setup lắp
	 * đặt / Tháo dỡ: *"sai hạng mục rồi"*. Đúng — hai cái đó là việc của Kỹ thuật. Marketing
	 * lập dự án cho *"Bán vé sớm, Khai trương, Sự kiện"*.
	 *
	 * 🔴 ĐÂY LÀ LOẠI LƯU XUỐNG SỔ, KHÔNG PHẢI NHÃN TRÊN MÀN. Khác hẳn việc đổi tên hai cái nút
	 *    ở bản 1.143.0 — chỗ ấy chỉ đổi chữ, còn `loai` giữ nguyên. Ở đây ba loại mới là ba
	 *    thứ có thật, cần đứng riêng trong báo cáo và bản xuất, nên chúng phải vào sổ.
	 *
	 * ⚠️ CHỈ THÊM, KHÔNG ĐỔI TÊN CÁI CŨ. 'Setup lắp đặt' và 'Tháo dỡ' đang nằm trên hàng trăm
	 *    dòng trong sổ; đổi chữ là mọi dòng cũ rơi ra ngoài mọi bộ lọc, và `loai_cp_mac_dinh()`
	 *    không tra được mã tài khoản cho chúng nữa.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */
	const LOAI_DU_AN = array( 'Setup lắp đặt', 'Tháo dỡ', 'Bán vé sớm', 'Khai trương', 'Sự kiện' );

	/**
	 * Dự án này thật ra là một ĐƠN chi phí cơ sở (theo tuần), không phải dự án Setup/Tháo dỡ.
	 *
	 * 🔴 MÀN PHẢI ĐỌC CỜ NÀY, KHÔNG TỰ SO CHUỖI. Anh Thắng 11/09/2026, nhìn khối duyệt lệnh:
	 *    *"Lệnh tạm ứng theo chi phí kỹ thuật chứ"* và *"Dự án tuần thì nó đâu có thười gian
	 *    setup"* — hai chỗ chữ gọi sai vì màn không có cách nào biết dòng ấy là đơn hay dự án.
	 *    Gửi cờ xuống thì đổi tên loại sau này là sửa một chỗ, không phải đi dò chuỗi khắp nơi.
	 */
	public static function la_don_coso( $loai ) {
		return self::LOAI_COSO === trim( (string) $loai );
	}

	/** Mã đơn chi phí cơ sở CHUNG (sổ xuyên suốt cũ) — '' nghĩa là sổ này không có. */
	public static function ma_coso_chung() {
		global $wpdb;
		$m = trim( (string) VHCPVP_Meta::get( self::MK_COSO_CHUNG, '' ) );
		if ( '-' === $m ) { return ''; }
		if ( '' !== $m ) { return self::find( $m ) ? $m : ''; }
		$t = VHCPVP_DB::t( 'da_index' );
		$r = VHCPVP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE loai=%s ORDER BY stt ASC LIMIT 1", 'Chi phí cơ sở' ) );
		if ( ! $r ) { return ''; }   // 🔴 KHÔNG tự tạo: hàm này bị gọi cả trong delete()
		VHCPVP_Meta::set( self::MK_COSO_CHUNG, (string) $r['ma_da'] );
		return (string) $r['ma_da'];
	}

	/** Dự án này có phải sổ chi phí cơ sở CHUNG không (đơn theo đợt thì không). */
	public static function la_coso_chung( $ma_da ) {
		$ma_da = trim( (string) $ma_da );
		if ( '' === $ma_da ) { return false; }
		return ( $ma_da === self::ma_coso_chung() );
	}

	/**
	 * Lập MỘT đơn chi phí cơ sở cho một TUẦN — đơn này gom nhiều gian, mỗi dòng một gian.
	 *
	 * 🔴 KHOẢNG NGÀY ĐI CÙNG LỜI GỌI TẠO, KHÔNG PHẢI LỜI GỌI THỨ HAI. Tạo đơn xong rồi mới gọi
	 *    tiếp `set_ky_da()` là hai lượt mạng cho một việc: lượt sau hỏng (rớt mạng, đóng tab)
	 *    thì đơn nằm đó KHÔNG có tuần, màn vẫn báo "đã tạo", và không ai biết thiếu.
	 *
	 * 🔴 KIỂM KHOẢNG NGÀY TRƯỚC KHI THÊM DÒNG. Kiểm sau là ngày ngược thì đơn đã sinh ra rồi,
	 *    chối cũng muộn — người dùng thấy lỗi mà trong sổ vẫn mọc thêm một đơn rác.
	 */
	public static function tao_don_coso( $ten, $nguoi, $tu = '', $den = '' ) {
		global $wpdb;
		$ten = VHCPVP_Util::san( $ten );
		if ( '' === $ten ) { return VHCPVP_Util::err( 'Nhập tên đợt chi phí cơ sở' ); }
		$tu  = trim( (string) $tu );
		$den = trim( (string) $den );
		if ( '' !== $tu && '' !== $den && $den < $tu ) {
			return VHCPVP_Util::err( 'Ngày kết thúc không được trước ngày bắt đầu.' );
		}
		/* Ghim trạng thái "đơn chung" TRƯỚC khi thêm đơn mới — xem chốt ⚠️ ở trên. */
		if ( '' === self::ma_coso_chung() ) { VHCPVP_Meta::set( self::MK_COSO_CHUNG, '-' ); }
		$ma = VHCPVP_Util::uid( 'DA' );
		$wpdb->insert( VHCPVP_DB::t( 'da_index' ), array(
			'ma_da'      => $ma,
			'ten'        => $ten,
			'loai'       => 'Chi phí cơ sở',
			'trang_thai' => 'Đang làm',
			'ngay_tao'   => VHCPVP_Util::now_sql(),
			'nguoi_tao'  => (string) $nguoi,
		) );
		if ( '' !== $tu || '' !== $den ) { VHCPVP_Meta::set_json( 'daKy_' . $ma, array( 'tu' => $tu, 'den' => $den ) ); }
		VHCPVP_Log::log_action( array(
			'actor'  => (string) ( '' !== $nguoi ? $nguoi : VHCPVP_Auth::nguoi() ),
			'role'   => VHCPVP_Auth::vai_tro(),
			'action' => 'Lập đơn chi phí cơ sở',
			'target' => $ma,
			'detail' => $ten . ( ( '' !== $tu || '' !== $den ) ? ( ' · ' . $tu . ' → ' . $den ) : '' ),
		) );
		return VHCPVP_Util::ok( array( 'maDA' => $ma, 'ten' => $ten, 'loai' => 'Chi phí cơ sở', 'sheet' => '', 'trangThai' => 'Đang làm', 'url' => '', 'kyDA' => array( 'tu' => $tu, 'den' => $den ) ) );
	}

	/** ensureCoSoChung(): chi phí cơ sở kỹ thuật = 1 "sheet" CHUNG duy nhất, xuyên suốt. */
	public static function ensure_co_so_chung( $nguoi ) {
		global $wpdb;
		$ma_cu = self::ma_coso_chung();
		if ( '' !== $ma_cu ) {
			$r = self::find( $ma_cu );
			return VHCPVP_Util::ok( array(
				'maDA'      => $r['ma_da'],
				'ten'       => $r['ten'],
				'loai'      => 'Chi phí cơ sở',
				'sheet'     => '',
				'trangThai' => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Đang làm' ),
				'url'       => '',
			) );
		}
		$ma  = VHCPVP_Util::uid( 'DA' );
		$ten = 'Chi phí cơ sở (Kỹ thuật · chung)';
		$wpdb->insert( VHCPVP_DB::t( 'da_index' ), array(
			'ma_da'      => $ma,
			'ten'        => $ten,
			'loai'       => 'Chi phí cơ sở',
			'trang_thai' => 'Đang làm',
			'ngay_tao'   => VHCPVP_Util::now_sql(),
			'nguoi_tao'  => (string) $nguoi,
		) );
		VHCPVP_Meta::set( self::MK_COSO_CHUNG, $ma );
		return VHCPVP_Util::ok( array( 'maDA' => $ma, 'ten' => $ten, 'loai' => 'Chi phí cơ sở', 'sheet' => '', 'trangThai' => 'Đang làm', 'url' => '' ) );
	}

	/**
	 * MỌI "ĐƠN" (hạng mục lớn) CỦA MỌI DỰ ÁN, GOM CHO KẾ TOÁN.
	 *
	 * Anh Thắng 10/09/2026: *"Chỗ duyệt tạm ứng và quyết toán của kế toán cũng sẽ hiện 2 bảng
	 * để kế toán phân biệt đơn theo cơ sở (theo tuần), đơn theo dự án (theo thời gian setup
	 * linh động mà nhân viên sẽ nhập vào)"*, và *"kế toán phải biết đơn nào hoàn thành và chưa
	 * hoàn thành để nhắc nhân viên chốt sớm để tránh sót và quên"*.
	 *
	 * 🔴 KHÔNG LỌC BỎ HẠNG MỤC ĐANG "nhap". Kế toán cần thấy CẢ những cái chưa ai đụng tới —
	 *    đó chính là loại bị quên. Lọc sạch cho gọn là giấu đúng thứ họ đi tìm.
	 *
	 * ⚠️ Tôn trọng tầm nhìn đơn vị như `list_du_an()`: dự án neo theo NGƯỜI TẠO.
	 */
	public static function list_don_hm() {
		$out    = array();
		$dv_xem = VHCPVP_DonVi::xem_duoc();
		foreach ( self::all_with_lines() as $r ) {
			if ( null !== $dv_xem
				&& ! VHCPVP_DonVi::duoc_xem( VHCPVP_DonVi::cua_nguoi( isset( $r['nguoi_tao'] ) ? $r['nguoi_tao'] : '' ) ) ) { continue; }
			$ma_da = (string) $r['ma_da'];
			$hm_all = self::get_hm( $ma_da );
			/* Bản đồ số đợt của dự án này — tính MỘT lần cho cả dự án, không tính lại theo từng
			   hạng mục (mỗi lượt là một lượt đọc sổ lệnh). Màn Duyệt in "đợt mấy" trên nhãn UNC
			   của từng hàng, mà lệnh bị trả không tính là một đợt — xem 🔴 ở `ds_dot()`. */
			$bd_tu = self::ban_do_so_dot( $ma_da );
			$bd_qt = self::ban_do_so_dot( $ma_da, 'qt' );
			/* Tiền của một "đơn": hạng mục có con thì tiền nằm ở CON, không có con thì ở chính
			   nó. Cộng cả hai là đếm hai lần — và đây là con số kế toán chuẩn bị tiền. */
			$con = array();
			foreach ( $r['lines'] as $x ) {
				$cap = trim( (string) $x['cap_cha'] );
				if ( '' !== $cap && '(Phát sinh)' !== $cap ) {
					$con[ $cap ] = ( isset( $con[ $cap ] ) ? $con[ $cap ] : 0 ) + VHCPVP_Util::num( $x['thuc_te'] );
				}
			}
			foreach ( $r['lines'] as $x ) {
				if ( ! self::is_real( $x ) ) { continue; }
				if ( trim( (string) $x['cap_cha'] ) !== '' ) { continue; }   // chỉ hạng mục LỚN
				$nd  = trim( (string) $x['noi_dung'] );
				$row = (int) $x['row_no'];
				$k   = (string) $row;
				$h   = isset( $hm_all[ $k ] ) ? self::hm_cua( $ma_da, $row ) : self::hm_cua( $ma_da, $row );
				$out[] = array(
					'maDA'      => $ma_da,
					'tenDA'     => (string) $r['ten'],
					'loaiDA'    => (string) $r['loai'],
					'isCoSo'    => self::la_don_coso( $r['loai'] ),
					'nguoiTao'  => isset( $r['nguoi_tao'] ) ? (string) $r['nguoi_tao'] : '',
					'row'       => $row,
					'noiDung'   => $nd,
					'gian'      => trim( (string) $x['gian'] ),
					'hinhThuc'  => trim( (string) $x['hinh_thuc'] ),
					'duToan'    => VHCPVP_Util::num( $x['du_toan'] ),
					'thucTe'    => ( isset( $con[ $nd ] ) && $con[ $nd ] > 0 ) ? $con[ $nd ] : VHCPVP_Util::num( $x['thuc_te'] ),
					'hm'        => $h,
					'dotHien'   => $bd_tu,
					'qtDotHien' => $bd_qt,
					'kyDA'      => self::get_ky_da( $ma_da ),
				);
			}
		}
		return VHCPVP_Util::ok( array( 'items' => $out ) );
	}

	public static function list_du_an() {
		$out = array();
		$sc_tong = VHCPVP_SoChi::tong_theo_du_an();   // 1 lệnh DB cho mọi dự án
		$dv_xem  = VHCPVP_DonVi::xem_duoc();
		/* ⚠️ HỎI MỘT LẦN, NGOÀI VÒNG LẶP. `la_coso_chung()` đọc meta mỗi lần gọi; gọi trong
		   vòng là thêm đúng một lệnh DB cho MỖI dự án, và `bench-queries.php` đỏ ngay. */
		$ma_chung = self::ma_coso_chung();
		foreach ( self::all_with_lines() as $r ) {
			/* Dự án neo theo NGƯỜI TẠO — xem chốt dài ở `don_vi_cua()`. */
			if ( null !== $dv_xem
				&& ! VHCPVP_DonVi::duoc_xem( VHCPVP_DonVi::cua_nguoi( isset( $r['nguoi_tao'] ) ? $r['nguoi_tao'] : '' ) ) ) { continue; }
			$lines = $r['lines'];
			$dt = 0; $tt = 0; $child = array();
			foreach ( $lines as $x ) {
				$cap = trim( (string) $x['cap_cha'] );
				if ( $cap !== '' && $cap !== '(Phát sinh)' ) { $child[ $cap ] = ( isset( $child[ $cap ] ) ? $child[ $cap ] : 0 ) + VHCPVP_Util::num( $x['thuc_te'] ); }
			}
			$cs_co = array();
			foreach ( $lines as $x ) {
				if ( ! self::is_real( $x ) ) { continue; }
				$nd  = trim( (string) $x['noi_dung'] );
				$cap = trim( (string) $x['cap_cha'] );
				$g   = trim( (string) $x['gian'] );
				if ( '' !== $g ) { $cs_co[ mb_strtolower( $g ) ] = 1; }
				if ( $cap === '' ) {
					$dt += VHCPVP_Util::num( $x['du_toan'] );
					if ( ! ( isset( $child[ $nd ] ) && $child[ $nd ] > 0 ) ) { $tt += VHCPVP_Util::num( $x['thuc_te'] ); }
				} else {
					$tt += VHCPVP_Util::num( $x['thuc_te'] );
				}
			}
			// Cộng thêm phần nằm ở SỔ CHI PHÍ mang mã dự án này (khớp theo mã hoặc theo tên)
			$sc = self::sc_cua( $sc_tong, $r['ma_da'], $r['ten'] );
			$out[] = array(
				'maDA'       => $r['ma_da'],
				'ten'        => $r['ten'],
				'loai'       => $r['loai'],
				'sheet'      => '',
				'trangThai'  => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Đang làm' ),
				'ngayTao'    => VHCPVP_Util::fmt( $r['ngay_tao'] ),
				'nguoi'      => $r['nguoi_tao'],
				'tongDuToan' => $dt + $sc['duToan'],
				'tongThucTe' => $tt + $sc['tien'],
				'soDongSoChi' => $sc['n'],
				/* Số dòng CÓ THẬT của dự án — màn cần con số này để biết sổ chung có rỗng
				   không mà bày nút 🗑. Đếm ở đây vì `$lines` đã đọc sẵn, khỏi thêm lệnh DB. */
				'soDong'     => (int) count( array_filter( $lines, array( __CLASS__, 'is_real' ) ) ),
				'chenh'      => ( $tt + $sc['tien'] ) - ( $dt + $sc['duToan'] ),
				/* Sổ CHUNG xuyên suốt vs đơn cơ sở theo đợt — danh sách phải phân biệt được để
				   đừng bày nút 🗑 lên cái không xoá nổi. */
				'cosoChung'  => ( '' !== $ma_chung && (string) $r['ma_da'] === $ma_chung ),
				/* Đếm gian: một đơn cơ sở gom nhiều gian, con số này nói ngay đơn ấy rải mấy nơi. */
				'soCoSo'     => count( $cs_co ),
				'url'        => '',
			);
		}
		/* 🔴 Ô CHỌN CƠ SỞ KHÔNG ĐƯỢC BÀY GIAN CỦA BÊN KIA — anh Thắng 11/09/2026: *"Thêm đơn vị
		   KVC để tách ra được không. Vì để bên K&H vẫn thấy bên Posh"*, kèm ảnh ô "Gian / cơ
		   sở" của đơn Kỹ thuật xổ ra cả "POSH MN CGV VINCOM LANDMARK".

		   Danh sách này trước đây lấy THẲNG toàn bộ danh mục cơ sở, nên mọi lớp tách đơn vị
		   dựng công phu ở `VHCPVP_DonVi` đều vô nghĩa ngay tại ô người ta gõ hằng ngày: chọn
		   nhầm một gian của bên kia là dòng chi rơi sang sổ của họ.

		   ⚠️ `coso_xem_duoc()` trả `null` nghĩa là XEM CẢ (Admin · Quản lý · Kế toán) — lúc ấy
		      phải bày đủ, không phải bày rỗng. */
		$coso = VHCPVP_DonVi::coso_xem_duoc();
		if ( null === $coso ) {
			$coso = array();
			foreach ( VHCPVP_Cfg::cfg_static()['coso'] as $x ) { $coso[] = $x['ten']; }
		}
		return VHCPVP_Util::ok( array( 'items' => array_reverse( $out ), 'coso' => $coso ) );
	}

	/** Lấy phần sổ chi phí của 1 dự án trong bảng tổng (khớp theo mã dự án hoặc theo tên). */
	private static function sc_cua( $sc_tong, $ma_da, $ten ) {
		$k0 = array( 'tien' => 0, 'duToan' => 0, 'n' => 0 );
		foreach ( array( $ma_da, $ten ) as $key ) {
			$k = mb_strtolower( trim( (string) $key ) );
			if ( $k !== '' && isset( $sc_tong[ $k ] ) ) { return $sc_tong[ $k ]; }
		}
		return $k0;
	}

	public static function rename_du_an( $ma_da, $ten ) {
		global $wpdb;
		$ten = VHCPVP_Util::san( $ten );
		if ( $ten === '' ) { return VHCPVP_Util::err( 'Tên trống' ); }
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		if ( self::la_coso_chung( $ma_da ) ) { return VHCPVP_Util::err( 'Sổ Chi phí cơ sở CHUNG không đổi tên — đơn cơ sở theo đợt thì đổi được' ); }
		$wpdb->update( VHCPVP_DB::t( 'da_index' ), array( 'ten' => $ten ), array( 'ma_da' => (string) $ma_da ) );
		return VHCPVP_Util::ok( array( 'ten' => $ten ) );
	}

	// ---------------------------------------------------------------- chi tiết

	public static function get_du_an( $ma_da ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }

		$lines = array();
		foreach ( self::lines_of( $ma_da ) as $r ) {
			if ( ! self::is_real( $r ) ) { continue; }
			$lines[] = array(
				'row'       => (int) $r['row_no'],
				'noiDung'   => (string) $r['noi_dung'],
				'duToan'    => VHCPVP_Util::num( $r['du_toan'] ),
				'thucTe'    => VHCPVP_Util::num( $r['thuc_te'] ),
				'soLuong'   => VHCPVP_Util::num( $r['so_luong'] ),
				'donGia'    => VHCPVP_Util::num( $r['don_gia'] ),
				'thanhTien' => VHCPVP_Util::num( $r['thanh_tien'] ),
				'vat'       => (string) $r['vat'],
				'anh'       => (string) $r['anh'],
				'gian'      => (string) $r['gian'],
				'note'      => (string) $r['note'],
				'capCha'    => trim( (string) $r['cap_cha'] ),
				'hinhThuc'  => trim( (string) $r['hinh_thuc'] ),
				'hoSo'      => trim( (string) $r['ho_so'] ),
				'loaiCp'    => (string) $r['loai_cp'],
				'tkNo'      => (string) $r['tk_no'],
				'tkCo'      => (string) $r['tk_co'],
				'maDt'      => (string) $r['ma_dt'],
			);
			/* Trạng thái "đơn" của hạng mục lớn — mục con đi theo cha nên không mang riêng. */
			if ( trim( (string) $r['cap_cha'] ) === '' ) {
				$lines[ count( $lines ) - 1 ]['hm'] = self::hm_cua( $ma_da, (int) $r['row_no'] );
			}
		}

		$child_tt = array(); $parent_ht = array(); $parent_gian = array();
		foreach ( $lines as $l ) {
			if ( $l['capCha'] !== '' && $l['capCha'] !== '(Phát sinh)' ) { $child_tt[ $l['capCha'] ] = ( isset( $child_tt[ $l['capCha'] ] ) ? $child_tt[ $l['capCha'] ] : 0 ) + $l['thucTe']; }
			if ( $l['capCha'] === '' ) {
				$parent_ht[ $l['noiDung'] ]   = $l['hinhThuc'];
				$parent_gian[ $l['noiDung'] ] = trim( (string) $l['gian'] );
			}
		}
		foreach ( $lines as $i => $l ) {
			if ( $l['capCha'] !== '' && $l['capCha'] !== '(Phát sinh)' && isset( $parent_ht[ $l['capCha'] ] ) && $parent_ht[ $l['capCha'] ] !== '' ) {
				$lines[ $i ]['hinhThuc'] = $parent_ht[ $l['capCha'] ];
			}
			/* Gian cũng thừa hưởng của cha y như hình thức chi: mục con của một hạng mục ở gian
			   A thì cũng nằm ở gian A. Không thừa hưởng thì bảng "theo cơ sở" dồn hết mục con
			   vào một rổ "(chưa ghi gian)" — đúng chỗ tiền thật nằm, sai chỗ người ta đi tìm. */
			if ( $l['capCha'] !== '' && $l['capCha'] !== '(Phát sinh)' && trim( (string) $l['gian'] ) === ''
				&& isset( $parent_gian[ $l['capCha'] ] ) && $parent_gian[ $l['capCha'] ] !== '' ) {
				$lines[ $i ]['gian'] = $parent_gian[ $l['capCha'] ];
			}
		}

		/* Các LỆNH tạm ứng của dự án — nhân viên nhìn là biết đợt nào tới đâu. */
		$lenh = array();
		$ten_cua = array();
		foreach ( self::lines_of( $ma_da ) as $r2 ) { $ten_cua[ (int) $r2['row_no'] ] = trim( (string) $r2['noi_dung'] ); }
		$da_xin = 0; $da_chi = 0;
		/* `du_kien_sau` = phần của lệnh chưa được xếp vào đợt nào — anh Thắng gọi là "dự kiến
		   đợt tiếp theo". `da_vao_lenh` giữ NGHĨA CŨ của `da_xin` (tổng mọi lệnh đã gửi) vì
		   dòng chân thẻ vẫn cần nó để nói "đã đưa hết hạng mục vào lệnh chưa" — một câu
		   khác hẳn "đã xin nhận bao nhiêu". Gộp hai câu vào một con số là chỗ vừa phải tách. */
		$du_kien_sau = 0; $da_vao_lenh = 0;
		$lenhQT = array(); $qt_gui = 0; $qt_chot = 0;
		foreach ( self::ds_dot( $ma_da, 'qt' ) as $q ) {
			$tq = array();
			foreach ( $q['rows'] as $rw ) { if ( isset( $ten_cua[ $rw ] ) ) { $tq[] = $ten_cua[ $rw ]; } }
			$q['tenHM'] = $tq;
			$lenhQT[] = $q;
			/* 🔴 LỆNH BỊ TRẢ LẠI KHÔNG TÍNH. Nó đã quay về cho nhân viên sửa; cộng vào là con số
			   phình lên bởi lệnh không còn tồn tại, rồi họ gửi lại là cộng thêm lần nữa. */
			if ( in_array( $q['tt'], array( 'xin', 'xong' ), true ) ) { $qt_gui += $q['soTien']; }
			/* 🔴 "ĐÃ QUYẾT TOÁN" LÀ ĐÃ CHỐT SỔ, không phải đã gửi. Gửi rồi mà kế toán chưa đối
			   chiếu thì khoản ấy vẫn treo trên TK 141 — báo là đã quyết toán là báo sai. */
			if ( 'xong' === $q['tt'] ) { $qt_chot += $q['soTien']; }
		}
		foreach ( self::ds_dot( $ma_da ) as $d ) {
			$tn = array();
			foreach ( $d['rows'] as $rw ) { if ( isset( $ten_cua[ $rw ] ) ) { $tn[] = $ten_cua[ $rw ]; } }
			$d['tenHM'] = $tn;
			$lenh[] = $d;
			/* 🔴 LỆNH BỊ TRẢ LẠI KHÔNG TÍNH LÀ ĐÃ XIN. Nó đã quay về cho nhân viên sửa; cộng vào
			   là con số "đã xin" phình lên bởi những lệnh không còn tồn tại, rồi nhân viên gửi
			   lại là cộng thêm lần nữa. */
			if ( in_array( $d['tt'], array( 'xin', 'duyet', 'ung' ), true ) ) {
				/* ═══════════════════════════════════════════════════════════════════════════════
				 * 🔴 "ĐÃ XIN TẠM ỨNG" = TỔNG CÁC ĐỢT ĐÃ KHAI, KHÔNG PHẢI TỔNG CẢ LỆNH.
				 * ═══════════════════════════════════════════════════════════════════════════════
				 * Anh Thắng 17/09/2026, nhìn thẻ ghi 68.790.000đ: *"Số tiền xin tạm ứng đợt 1,
				 * chứ xin tổng vẫn chưa mà"*; rồi chốt: *"Tạm ứng xin đợt 1, đợt 2… còn số nào
				 * chưa lên thì ghi là dự kiến đợt tiếp theo"*.
				 *
				 * Một LỆNH xin duyệt chi cho cả lô hạng mục (68.79tr), nhưng nhân viên chỉ xin
				 * CẦM VỀ theo từng đợt (10tr ngày 03/09, 20tr ngày 10/09). Cộng cả lệnh vào ô
				 * "đã xin" là nói nhân viên đã xin 68.79tr trong khi họ mới xin nhận 30tr — kế
				 * toán đọc con số ấy để liệu tiền, nên nó phóng đại đúng khoản phải chuẩn bị.
				 *
				 * ⚠️ LỆNH KHÔNG KHAI LỊCH THÌ LẤY TRỌN SỐ LỆNH. Đó là ca thường nhất — nhận một
				 *    lần, không chia đợt — và ở đó "đã xin" đúng bằng cả lệnh. Lấy tổng lịch
				 *    (bằng 0) là mọi lệnh không chia đợt bỗng thành "chưa xin đồng nào".
				 * ⚠️ LỊCH CÓ DÒNG MÀ KHÔNG GHI SỐ TIỀN (chỉ hẹn ngày) cũng rơi về trọn số lệnh:
				 *    tổng lịch bằng 0 thì nó không nói được gì về tiền. */
				$tong_lich = 0;
				foreach ( (array) $d['lich'] as $lx ) {
					$tong_lich += VHCPVP_Util::num( isset( $lx['soTien'] ) ? $lx['soTien'] : 0 );
				}
				if ( $tong_lich > 0 ) {
					$da_xin += $tong_lich;
					$con_lich = $d['soTien'] - $tong_lich;
					if ( $con_lich > 0 ) { $du_kien_sau += $con_lich; }
				} else {
					$da_xin += $d['soTien'];
				}
				$da_vao_lenh += $d['soTien'];
			}
			/* 🔴 "ĐÃ CHI" ĐỌC THEO TIỀN THẬT ĐÃ ĐƯA, KHÔNG THEO TRẠNG THÁI LỆNH. Từ 1.194.0 kế
			   toán cấp được làm nhiều lần: lệnh 48tr mới đưa 20tr thì tiền ẤY ĐÃ RA KHỎI KÉT,
			   trong khi lệnh còn ở 'duyet'. Đếm theo trạng thái là bỏ sót đúng phần đang dở dang,
			   và con số "còn treo trên TK 141" nói thiếu. Lượt cấp trọn cũng ghi vào `daCap`
			   (xem `dat_tt_dot`), nên lối cũ ra đúng con số cũ. */
			/* 🔴 LỆNH ĐÃ THU HỒI THÌ THÔI TÍNH LÀ ĐÃ CHI. Anh Thắng 18/09/2026 chốt *"coi như
			   chưa chi — gỡ khỏi TK 141"*. Mấy lượt cấp tiền vẫn nằm nguyên trong sổ của lệnh
			   ấy để tra; chỉ con số TỔNG thôi đếm chúng. Không gỡ thì thu hồi xong màn vẫn báo
			   "còn treo 68.790.000đ" cho một lệnh không còn tồn tại. */
			if ( 'tra' !== $d['tt'] ) { $da_chi += self::da_cap_tong( $d ); }
		}

		/* 🔴 DỰ KIẾN TẠM ỨNG = tổng tiền của mọi hạng mục 💰 NV TỰ TRẢ, kể cả cái còn nháp.
		   Anh Thắng: *"Dự kiến tạm ứng tổng đơn"* — con số này trả lời "cả đơn này rốt cuộc phải
		   ứng ra bao nhiêu", để kế toán liệu tiền trước khi nhân viên bấm xin.
		   ⚠️ BỎ KHOẢN 🏢 TRỰC TIẾP. Tiền ấy kế toán trả thẳng nhà cung cấp, không bao giờ đi qua
		      đường tạm ứng — gộp vào là báo một con số tạm ứng lớn hơn thực tế phải chuẩn bị. */
		$du_kien = 0;
		foreach ( self::lines_of( $ma_da ) as $r3 ) {
			if ( ! self::is_real( $r3 ) ) { continue; }
			if ( trim( (string) $r3['cap_cha'] ) !== '' ) { continue; }
			if ( 'Trực tiếp' === trim( (string) $r3['hinh_thuc'] ) ) { continue; }
			/* ⚠️ `tien_hm_du_kien`, KHÔNG phải `tien_hm`. Chú thích ngay trên nói con số này để
			   kế toán liệu tiền TRƯỚC khi nhân viên bấm xin — mà `tien_hm()` chỉ đọc thực tế,
			   nên trước lúc xin nó luôn bằng 0. Xem khối 🔴 ở `tien_hm_du_kien()`. */
			$du_kien += self::tien_hm_du_kien( $ma_da, (int) $r3['row_no'] );
		}

		$dt = 0; $tt = 0; $du_tu = 0; $du_tt = 0; $tt_tu = 0; $tt_tt = 0; $tt_vat = 0; $tt_novat = 0;
		/* 🔴 MỘT ĐƠN NHIỀU CƠ SỞ -> PHẢI NÓI ĐƠN NÀY RẢI QUA NHỮNG GIAN NÀO. Anh Thắng 11/09/2026:
		   *"1 đơn nhiều cơ sở chung 1 đơn"*. Tổng cả đơn thì kế toán đã có; thứ họ không có là
		   "gian nào bao nhiêu" — mà đó chính là con số đi vào mã tài khoản theo mảng. */
		$theo_cs = array();
		foreach ( $lines as $l ) {
			$has_child = ( isset( $child_tt[ $l['noiDung'] ] ) && $child_tt[ $l['noiDung'] ] > 0 );
			$is_pay    = ( $l['capCha'] !== '' ) || ( $l['capCha'] === '' && ! $has_child );
			$is_tt     = ( $l['hinhThuc'] === 'Trực tiếp' );
			/* Gom theo gian dùng ĐÚNG luật `is_pay` của phần cộng tổng ngay dưới: hạng mục có
			   con thì tiền nằm ở con. Gom rời một luật khác là hai nơi cùng một phép tính, và
			   hai nơi thì lệch — tổng các gian không bằng tổng đơn, không ai biết vì sao. */
			$_g = trim( (string) $l['gian'] );
			if ( ! isset( $theo_cs[ $_g ] ) ) { $theo_cs[ $_g ] = array( 'coso' => $_g, 'duToan' => 0, 'thucTe' => 0, 'soDong' => 0 ); }
			$theo_cs[ $_g ]['soDong']++;
			if ( $l['capCha'] === '' ) { $theo_cs[ $_g ]['duToan'] += $l['duToan']; }
			if ( $is_pay ) { $theo_cs[ $_g ]['thucTe'] += $l['thucTe']; }
			if ( $l['capCha'] === '' ) {
				$dt += $l['duToan'];
				if ( ! $has_child ) { $tt += $l['thucTe']; }
				if ( $is_tt ) { $du_tt += $l['duToan']; } else { $du_tu += $l['duToan']; }
			} else {
				$tt += $l['thucTe'];
			}
			if ( $is_pay ) {
				if ( $is_tt ) {
					$tt_tt += $l['thucTe'];
					if ( mb_strpos( (string) $l['vat'], 'Có' ) !== false ) { $tt_vat += $l['thucTe']; } else { $tt_novat += $l['thucTe']; }
				} else {
					$tt_tu += $l['thucTe'];
				}
			}
		}

		// Phần nằm ở sổ chi phí: khớp theo MÃ dự án, không có thì khớp theo TÊN
		$sc_lines = VHCPVP_SoChi::theo_du_an( (string) $ma_da );
		if ( ! count( $sc_lines ) ) { $sc_lines = VHCPVP_SoChi::theo_du_an( (string) $f['ten'] ); }
		$sc_tien = 0; $sc_du_toan = 0;
		foreach ( $sc_lines as $x ) {
			$sc_tien    += VHCPVP_Util::num( $x['soTien'] );
			$sc_du_toan += VHCPVP_Util::num( $x['duToan'] );
		}

		$st  = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		$pay = self::get_pay( $ma_da );   // đọc MỘT lần, dùng cho cả 'pay' lẫn 'payTong'
		return VHCPVP_Util::ok( array(
			'maDA'            => (string) $ma_da,
			'ten'             => $f['ten'],
			'loai'            => $f['loai'],
			'trangThai'       => $st,
			'url'             => '',
			'isCoSo'          => self::la_don_coso( $f['loai'] ),
			/* Sổ CHUNG xuyên suốt thì không duyệt / không đóng / không xoá; đơn cơ sở theo đợt
			   đi trọn luồng như mọi dự án khác. Màn đọc cờ này chứ KHÔNG suy từ `isCoSo` — suy
			   ở màn là khai lại một luật đã nằm ở `la_coso_chung()`, hai nơi rồi sẽ lệch. */
			'cosoChung'       => self::la_coso_chung( $ma_da ),
			'theoCoSo'        => array_values( $theo_cs ),
			// Dự án chi trực tiếp: chỉ còn 2 trạng thái thật là Đang làm / Đã đóng.
			// Chưa đóng thì nhập được — không còn khoá theo bước duyệt tạm ứng.
			'editable'        => ( $st !== 'Đã đóng' ),
			'pending'         => false,
			'approved'        => ( $st !== 'Đã đóng' ),
			'thiCong'         => ( $st !== 'Đã đóng' ),
			'closed'          => ( $st === 'Đã đóng' ),
			'lines'           => $lines,
			// Dòng chi của dự án nay nằm ở SỔ CHI PHÍ (mang mã dự án). Không cộng vào đây
			// thì gian nào cũng hiện 0đ dù dữ liệu đã nạp xong.
			'soChi'           => $sc_lines,
			'tongSoChi'       => $sc_tien,
			// Tách riêng để màn dự án ghi rõ tổng gồm những gì — đối chiếu hệ cũ mới biết
			// lệch ở bảng hạng mục hay ở sổ chi phí.
			'duToanSoChi'     => $sc_du_toan,
			'tongDuToan'      => $dt + $sc_du_toan,
			'tongThucTe'      => $tt + $sc_tien,
			'chenh'           => ( $tt + $sc_tien ) - ( $dt + $sc_du_toan ),
			/* Con số dự phòng của kế toán + phần đã ứng thật, để màn tính ra "còn dự phòng". */
			'duToanDA'        => self::get_du_toan_da( $ma_da ),
			'kyDA'            => self::get_ky_da( $ma_da ),
			'lenh'            => $lenh,
			'lenhQT'          => $lenhQT,
			/* Bản đồ `số đợt trong sổ` -> `số đợt hiện ra`. Màn hình in số đợt ở BỐN chỗ (bảng
			   lệnh, bảng quyết toán, nhãn UNC trên hàng hạng mục, huy hiệu "đã gửi QT đợt"); gửi
			   một bản đồ chung thì bốn chỗ ấy không thể lệch nhau. Xem 🔴 ở `ds_dot()`. */
			'dotHien'         => self::ban_do_so_dot( $ma_da ),
			'qtDotHien'       => self::ban_do_so_dot( $ma_da, 'qt' ),
			'qtDaGui'         => $qt_gui,
			'qtDaChot'        => $qt_chot,
			'duKienTU'        => $du_kien,
			'daXinTU'         => $da_xin,
			'duKienDotSau'    => $du_kien_sau,
			'daVaoLenh'       => $da_vao_lenh,
			'daChiTU'         => $da_chi,
			'canTamUng'       => $du_tu,
			'traTrucTiep'     => $du_tt,
			'ttTamUng'        => $tt_tu,
			'ttTrucTiep'      => $tt_tt,
			'ttTrucTiepVAT'   => $tt_vat,
			'ttTrucTiepNoVAT' => $tt_novat,
			'thieuTamUng'     => $tt_tu - $du_tu,
			'thieuTrucTiep'   => $tt_tt - $du_tt,
			'pay'             => $pay,
			/* Tổng ĐÃ CHI theo từng phần — màn và bài kiểm đọc số này chứ không tự cộng lại.
			   Tự cộng ở màn là hai nơi cùng giữ một phép tính, và hai nơi thì lệch. */
			'payTong'         => array(
				'tamUng'    => array( 'tu' => self::pay_tong( $pay, 'tamUng', 'tu' ),
				                      'tt' => self::pay_tong( $pay, 'tamUng', 'tt' ) ),
				'quyetToan' => array( 'tu' => self::pay_tong( $pay, 'quyetToan', 'tu' ),
				                      'tt' => self::pay_tong( $pay, 'quyetToan', 'tt' ) ),
			),
			'noiDungList'     => self::nd_list( $f['loai'] ),
		) );
	}

	// ---------------------------------------------------------------- gợi ý hạng mục con

	private static function nd_list( $loai ) {
		$o = VHCPVP_Meta::get_json( 'da_ndlist_v1', array() );
		return isset( $o[ $loai ] ) ? $o[ $loai ] : array();
	}

	private static function push_nd( $loai, $nd ) {
		$nd = trim( (string) $nd );
		if ( $nd === '' ) { return; }
		$o = VHCPVP_Meta::get_json( 'da_ndlist_v1', array() );
		$a = isset( $o[ $loai ] ) ? (array) $o[ $loai ] : array();
		$low = mb_strtolower( $nd );
		foreach ( $a as $x ) { if ( mb_strtolower( (string) $x ) === $low ) { return; } }
		array_unshift( $a, $nd );
		if ( count( $a ) > 300 ) { $a = array_slice( $a, 0, 300 ); }
		$o[ $loai ] = $a;
		VHCPVP_Meta::set_json( 'da_ndlist_v1', $o );
	}

	// ---------------------------------------------------------------- kế toán chi tiền

	public static function get_pay( $ma_da ) {
		return VHCPVP_Meta::get_json( 'daPay_' . $ma_da, array() );
	}

	/**
	 * TỔNG DỰ TOÁN CẢ DỰ ÁN — con số kế toán DỰ PHÒNG tiền.
	 *
	 * Anh Thắng 10/09/2026: *"Thêm ô nhập tổng dự toán (nó là con số dự tính tổng dự án để làm
	 * để kế toán biết dự phòng. Chứ nó chưa phải là con tạm ứng, con số tạm ứng là tổng thực tế
	 * sau khi lên đơn. Sau khi kế toán tạm ứng lần 1, sẽ trừ này ra để còn biết thừa thiếu"*.
	 *
	 * 🔴 KHÁC HẲN "tổng dự toán hạng mục". Hạng mục cộng lên là số đã khai chi tiết; con số này
	 *    là ước lượng CẢ dự án, gõ một lần lúc mới mở, thường lớn hơn vì còn phần chưa khai.
	 *    Trộn hai thứ làm một là kế toán dự phòng theo con số chi tiết chưa đủ, rồi thiếu tiền
	 *    đúng lúc đang thi công.
	 *
	 * ⚠️ Cũng KHÔNG phải số tạm ứng. Tạm ứng là tiền THẬT đã chi, ghi ở `confirm_pay()`; con số
	 *    này chỉ để trừ ra mà biết còn dự phòng bao nhiêu.
	 */
	public static function get_du_toan_da( $ma_da ) {
		return VHCPVP_Util::num( VHCPVP_Meta::get( 'daDuToan_' . $ma_da, 0 ) );
	}

	/**
	 * THỜI GIAN SETUP — anh Thắng 10/09/2026: *"Bổ sung thêm thời gian setup (Từ ngày đến
	 * ngày)"*, đúng nguyên lý *"Đơn theo thời gian, chứ không theo tuần"* anh nêu từ đầu.
	 *
	 * ⚠️ KHÔNG ÉP PHẢI CÓ. Dự án đang chạy dở chưa ai gõ khoảng ngày; bắt buộc là mọi dự án cũ
	 *    đỏ lên một lỗi mà không ai gây ra.
	 */
	public static function get_ky_da( $ma_da ) {
		$o = VHCPVP_Meta::get_json( 'daKy_' . $ma_da, array() );
		return array(
			'tu'  => isset( $o['tu'] ) ? (string) $o['tu'] : '',
			'den' => isset( $o['den'] ) ? (string) $o['den'] : '',
		);
	}

	public static function set_ky_da( $ma_da, $tu, $den, $nguoi = '' ) {
		if ( ! self::find( $ma_da ) ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$tu  = trim( (string) $tu );
		$den = trim( (string) $den );
		/* 🔴 NGÀY KẾT THÚC KHÔNG ĐƯỢC TRƯỚC NGÀY BẮT ĐẦU. Khoảng ngày ngược làm mọi phép "dự án
		   này kéo dài bao lâu" ra số âm, và không có gì trên màn nói vì sao. So bằng chuỗi
		   yyyy-mm-dd là đúng thứ tự thời gian, khỏi phải dựng đối tượng ngày. */
		if ( '' !== $tu && '' !== $den && $den < $tu ) {
			return VHCPVP_Util::err( 'Ngày kết thúc không được trước ngày bắt đầu.' );
		}
		VHCPVP_Meta::set_json( 'daKy_' . $ma_da, array( 'tu' => $tu, 'den' => $den ) );
		VHCPVP_Log::log_action( array(
			'actor'  => (string) ( '' !== $nguoi ? $nguoi : VHCPVP_Auth::nguoi() ),
			'role'   => VHCPVP_Auth::vai_tro(),
			'action' => 'Đặt thời gian setup dự án',
			'target' => (string) $ma_da,
			'detail' => $tu . ' → ' . $den,
		) );
		return VHCPVP_Util::ok( array( 'ky' => array( 'tu' => $tu, 'den' => $den ) ) );
	}

	public static function set_du_toan_da( $ma_da, $so, $nguoi = '' ) {
		if ( ! self::find( $ma_da ) ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$n = VHCPVP_Util::num( $so );
		/* 🔴 SỐ ÂM LÀ VÔ NGHĨA, và nó lặng lẽ làm phần "còn dự phòng" phình ra. Chối thẳng. */
		if ( $n < 0 ) { return VHCPVP_Util::err( 'Tổng dự toán không được âm.' ); }
		VHCPVP_Meta::set( 'daDuToan_' . $ma_da, $n );
		VHCPVP_Log::log_action( array(
			'actor'  => (string) ( '' !== $nguoi ? $nguoi : VHCPVP_Auth::nguoi() ),
			'role'   => VHCPVP_Auth::vai_tro(),
			'action' => 'Đặt tổng dự toán dự án',
			'target' => (string) $ma_da,
			'detail' => (string) $n,
		) );
		return VHCPVP_Util::ok( array( 'duToanDA' => $n ) );
	}

	public static function approve_date( $ma_da ) {
		return (string) VHCPVP_Meta::get( 'daApp_' . $ma_da, '' );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * TẠM ỨNG NHIỀU LẦN.
	 *
	 * Anh Thắng 10/09/2026, nói về đơn của bộ phận Kỹ thuật: *"Đơn có tạm ứng nhiều lần"*.
	 *
	 * 🔴 TRƯỚC BẢN NÀY MỖI (giai đoạn × loại) CHỈ GIỮ MỘT BẢN GHI, và lượt ứng thứ hai ĐÈ MẤT
	 *    lượt đầu — không báo gì, không hỏi gì. Số "đã ứng" tụt xuống còn đúng lần cuối, nên
	 *    phần bù/thu ở quyết toán tính trên một con số nhỏ hơn thực tế đã chi. Tiền thật.
	 *
	 * 🔴 DỮ LIỆU CŨ LÀ MỘT ĐỐI TƯỢNG, DỮ LIỆU MỚI LÀ MỘT DANH SÁCH. Mọi chỗ đọc phải đi qua
	 *    `pay_ds()` — nó nhận cả hai dạng. Đọc thẳng `$p[$phase][$loai]['amount']` thì với dự án
	 *    cũ vẫn ra số đúng, còn dự án mới ra `null`: hỏng đúng ở những dự án vừa dùng tính năng
	 *    mới, mà mắt nhìn bảng cũ thì thấy bình thường.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */

	/** Các lần chi của một (giai đoạn × loại), luôn trả về DANH SÁCH — nhận cả dạng cũ. */
	public static function pay_ds( $p, $phase, $loai ) {
		$v = ( isset( $p[ $phase ][ $loai ] ) && is_array( $p[ $phase ][ $loai ] ) ) ? $p[ $phase ][ $loai ] : null;
		if ( ! $v ) { return array(); }
		/* Dạng CŨ: một đối tượng có khoá 'amount'. Dạng MỚI: danh sách các đối tượng ấy. */
		if ( array_key_exists( 'amount', $v ) ) { return array( $v ); }
		$ra = array();
		foreach ( $v as $x ) { if ( is_array( $x ) && array_key_exists( 'amount', $x ) ) { $ra[] = $x; } }
		return $ra;
	}

	/** Tổng đã chi của một (giai đoạn × loại). */
	public static function pay_tong( $p, $phase, $loai ) {
		$t = 0;
		foreach ( self::pay_ds( $p, $phase, $loai ) as $x ) { $t += VHCPVP_Util::num( isset( $x['amount'] ) ? $x['amount'] : 0 ); }
		return $t;
	}

	public static function confirm_pay( $ma_da, $phase, $loai, $amount, $nguoi, $ghi_chu = '' ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		if ( ! in_array( (string) $phase, array( 'tamUng', 'quyetToan' ), true ) ) { return VHCPVP_Util::err( 'Giai đoạn không hợp lệ' ); }
		if ( ! in_array( (string) $loai, array( 'tu', 'tt' ), true ) ) { return VHCPVP_Util::err( 'Loại chi không hợp lệ' ); }
		$st = (string) $f['trang_thai'];
		if ( $st !== 'Đã duyệt' && $st !== 'Đã đóng' ) { return VHCPVP_Util::err( 'Chỉ chi tiền khi dự án đã kế toán duyệt tạm ứng' ); }
		/* 🔴 SỐ 0 KHÔNG PHẢI MỘT LẦN CHI. Ghi vào là danh sách có một dòng 0đ — nhìn thì như đã
		   chi, cộng vào thì không đổi gì, và người đọc sổ mất một lượt đi tìm xem nó là cái gì. */
		$so = VHCPVP_Util::num( $amount );
		if ( $so <= 0 ) { return VHCPVP_Util::err( 'Số tiền phải lớn hơn 0.' ); }

		$p  = self::get_pay( $ma_da );
		$ds = self::pay_ds( $p, $phase, $loai );
		$ds[] = array(
			'done'   => true,
			'amount' => $so,
			'date'   => VHCPVP_Util::now()->format( 'd/m/Y H:i' ),
			'by'     => (string) $nguoi,
			'ghiChu' => trim( (string) $ghi_chu ),
		);
		if ( ! isset( $p[ $phase ] ) || ! is_array( $p[ $phase ] ) ) { $p[ $phase ] = array(); }
		$p[ $phase ][ $loai ] = $ds;
		VHCPVP_Meta::set_json( 'daPay_' . $ma_da, $p );
		return VHCPVP_Util::ok( array( 'pay' => $p ) );
	}

	/**
	 * Bỏ MỘT lần chi. `$idx` là vị trí trong danh sách; để trống thì bỏ lần CUỐI.
	 *
	 * ⚠️ BỎ LẦN CUỐI, KHÔNG PHẢI BỎ SẠCH. Bản trước xoá cả ô — nay ô ấy có thể chứa năm lần
	 *    ứng, nên xoá sạch là mất bốn lần không ai định đụng tới. Hoàn tác là "gỡ cái vừa ghi",
	 *    đúng nghĩa cái nút ↩ trên màn.
	 */
	public static function unconfirm_pay( $ma_da, $phase, $loai, $idx = null ) {
		if ( ! self::find( $ma_da ) ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$p  = self::get_pay( $ma_da );
		$ds = self::pay_ds( $p, $phase, $loai );
		if ( ! $ds ) { return VHCPVP_Util::err( 'Chưa có lần chi nào để hoàn tác.' ); }
		$i = ( null === $idx || '' === $idx ) ? ( count( $ds ) - 1 ) : (int) $idx;
		if ( $i < 0 || $i >= count( $ds ) ) { return VHCPVP_Util::err( 'Không có lần chi thứ ' . ( (int) $idx + 1 ) . '.' ); }
		array_splice( $ds, $i, 1 );
		if ( ! isset( $p[ $phase ] ) || ! is_array( $p[ $phase ] ) ) { $p[ $phase ] = array(); }
		if ( $ds ) { $p[ $phase ][ $loai ] = $ds; }
		else { unset( $p[ $phase ][ $loai ] ); }
		VHCPVP_Meta::set_json( 'daPay_' . $ma_da, $p );
		return VHCPVP_Util::ok( array( 'pay' => $p ) );
	}

	// ------------------------------------------------- TRẠNG THÁI TỪNG HẠNG MỤC ("đơn")

	/**
	 * MỖI HẠNG MỤC LỚN LÀ MỘT "ĐƠN" CÓ ĐƯỜNG ĐI RIÊNG.
	 *
	 * Anh Thắng 10/09/2026: *"Nhân viên sẽ lên 10 đơn, xin tạm ứng, quản lý duyệt, kế toán gửi
	 * tạm ứng và gán ủy nhiệm chi lần 1, nếu đơn nào chính xác và hoàn thành sẽ tích hoàn thành
	 * và bổ sung hóa đơn nó sẽ khóa đơn đó lại và xác định đơn đó là chi thực tế"*, và *"Sau nv
	 * lên tiếp 10 đơn, thấy cần nhiều tiền tích vào xin tạm ứng lần 2"*.
	 *
	 * Đường đi:  nhap → xin → duyet → ung(đợt N) → xong(KHOÁ)
	 *                            ↘ tra (trả lại, quay về nhap)
	 *
	 * 🔴 KHOÁ LÀ KHOÁ THẬT. "Xong" nghĩa là đã có hoá đơn và đã chốt là chi thực tế; sửa được
	 *    nữa thì con số kế toán đã hạch toán đổi sau lưng họ. Chỉ Admin/Kế toán mở lại được.
	 *
	 * ⚠️ Lưu qua Meta, KHÔNG đổi sơ đồ CSDL — bảng dòng dự án đang có dữ liệu thật của nhiều
	 *    dự án đang chạy. Khoá là số dòng (`row`), thứ vẫn dùng để sửa/xoá dòng.
	 */
	const TT_HM = array( 'nhap', 'xin', 'duyet', 'ung', 'xong', 'tra' );

	public static function get_hm( $ma_da ) {
		$o = VHCPVP_Meta::get_json( 'daHM_' . $ma_da, array() );
		return is_array( $o ) ? $o : array();
	}

	/** Trạng thái của MỘT hạng mục. Chưa có gì thì là 'nhap' — dự án cũ vẫn đọc được. */
	public static function hm_cua( $ma_da, $row ) {
		$o = self::get_hm( $ma_da );
		$k = (string) (int) $row;
		$x = isset( $o[ $k ] ) && is_array( $o[ $k ] ) ? $o[ $k ] : array();
		return array(
			'tt'     => isset( $x['tt'] ) && in_array( $x['tt'], self::TT_HM, true ) ? $x['tt'] : 'nhap',
			'dot'    => isset( $x['dot'] ) ? (int) $x['dot'] : 0,
			'unc'    => isset( $x['unc'] ) ? (string) $x['unc'] : '',
			'hoaDon' => isset( $x['hoaDon'] ) ? (string) $x['hoaDon'] : '',
			'moc'    => isset( $x['moc'] ) && is_array( $x['moc'] ) ? $x['moc'] : array(),
			/* Lịch các đợt đi nhận tiền — anh Thắng 10/09/2026: *"kiểu gửi xin tạm ứng nhiều
			   lần, hoặc 1 lần, nếu 1 lần mà đi tạm ứng nhiều lần thì nv có thể lịch chọn ngày
			   đi tạm ứng lần 1,2,3"*. Xin MỘT lần nhưng nhận tiền làm nhiều đợt, mỗi đợt hẹn
			   một ngày; kế toán nhìn lịch mà chuẩn bị tiền cho đúng hôm. */
			'lich'   => isset( $x['lich'] ) && is_array( $x['lich'] ) ? array_values( $x['lich'] ) : array(),
			/* Hạng mục này đã nằm trong LỆNH QUYẾT TOÁN nào chưa (0 = chưa gửi). Anh Thắng:
			   *"khi đơn này đã xong, tích chọn để gửi quyết toán theo đơn"*. */
			'qtDot'  => isset( $x['qtDot'] ) ? (int) $x['qtDot'] : 0,
		);
	}

	/**
	 * Hình thức chi của một hạng mục LỚN — 'Trực tiếp' = kế toán trả thẳng nhà cung cấp.
	 *
	 * 🔴 ĐỌC TỪ SỔ, KHÔNG NHẬN TỪ MÀN. Nếu để màn gửi lên "đơn này là Trực tiếp" thì ai cũng
	 *    gắn cờ ấy được rồi đi thẳng tới bước khoá, bỏ qua cả duyệt lẫn cấp tạm ứng.
	 */
	public static function hinh_thuc_hm( $ma_da, $row ) {
		global $wpdb;
		$t = VHCPVP_DB::t( 'da_line' );
		$v = $wpdb->get_var( $wpdb->prepare( "SELECT hinh_thuc FROM $t WHERE ma_da=%s AND row_no=%d", (string) $ma_da, (int) $row ) );
		return trim( (string) $v );
	}

	/** Hạng mục do KẾ TOÁN trả thẳng NCC — không đi qua đường tạm ứng của nhân viên. */
	public static function hm_la_ncc( $ma_da, $row ) {
		return 'Trực tiếp' === self::hinh_thuc_hm( $ma_da, $row );
	}

	/** Hạng mục này có đang khoá không (đã chốt là chi thực tế). */
	public static function hm_khoa( $ma_da, $row ) {
		$h = self::hm_cua( $ma_da, $row );
		return 'xong' === $h['tt'];
	}

	private static function hm_ghi_( $ma_da, $row, $sua ) {
		$o = self::get_hm( $ma_da );
		$k = (string) (int) $row;
		$cu = self::hm_cua( $ma_da, $row );
		$moi = array_merge( $cu, $sua );
		/* Mốc thời gian từng bước — kế toán hỏi "cái này nằm đây bao lâu rồi" thì có chỗ tra. */
		$moi['moc'] = $cu['moc'];
		$moi['moc'][ $moi['tt'] ] = VHCPVP_Util::now()->format( 'd/m/Y H:i' ) . ' · ' . VHCPVP_Auth::nguoi();
		$o[ $k ] = $moi;
		VHCPVP_Meta::set_json( 'daHM_' . $ma_da, $o );
		VHCPVP_Log::log_action( array(
			'actor'  => VHCPVP_Auth::nguoi(),
			'role'   => VHCPVP_Auth::vai_tro(),
			'action' => 'Đổi trạng thái hạng mục dự án',
			'target' => (string) $ma_da . '#' . $k,
			'detail' => $cu['tt'] . ' → ' . $moi['tt'] . ( $moi['dot'] ? ( ' · lệnh ' . $moi['dot'] ) : '' ),
		) );
		return $moi;
	}

	/* ═════════════════════════════════════════════════════════════════════════════════════
	 * LỆNH TẠM ỨNG — NHIỀU HẠNG MỤC GỘP THÀNH MỘT LỆNH, MỘT SỐ TIỀN.
	 *
	 * 🔴 Anh Thắng: *"Trong 1 đơn chứ, trong 1 đơn mà nhiều lệnh tạm ứng, đơn nào bấm xin thì
	 *    nó tổng tổng tạm ứng cần xin"*.
	 *
	 *    Bản trước hiểu sai: mỗi hạng mục lớn thành MỘT đơn riêng đi tới kế toán. Bốn hạng mục
	 *    là bốn lần duyệt, bốn lần cấp tiền, bốn tờ uỷ nhiệm chi — cho một đợt setup. Với mười
	 *    hạng mục thì thành hai mươi lượt bấm, và tiền đi làm mười lệnh chuyển khoản.
	 *
	 *    Đúng ra: MỘT DỰ ÁN LÀ MỘT ĐƠN. Nhân viên tích mấy hạng mục cần tiền, bấm một cái →
	 *    thành MỘT LỆNH tạm ứng, số tiền là TỔNG các hạng mục ấy. Quản lý duyệt cả lệnh, kế
	 *    toán cấp cả lệnh kèm MỘT uỷ nhiệm chi. Lần sau cần thêm tiền thì tích tiếp mấy hạng
	 *    mục khác → lệnh đợt 2. Đúng chữ anh Thắng: *"Sau nv lên tiếp 10 đơn, thấy cần nhiều
	 *    tiền tích vào xin tạm ứng lần 2"*.
	 *
	 * 🔴 SỐ TIỀN CHỐT LÚC GỬI, KHÔNG TÍNH LẠI LÚC ĐỌC. Anh Thắng: *"con số tạm ứng là tổng thực
	 *    tế sau khi lên đơn"*. Nếu tính lại mỗi lần mở màn thì nhân viên sửa một dòng sau khi
	 *    gửi là con số quản lý đã duyệt tự đổi sau lưng họ — duyệt 20 triệu, cấp ra 25 triệu.
	 *
	 * ⚠️ Lưu qua Meta `daDot_<maDA>`, không đổi sơ đồ CSDL.
	 * ═════════════════════════════════════════════════════════════════════════════════════ */
	const TT_DOT = array( 'xin', 'duyet', 'ung', 'tra' );
	/* 🔴 QUYẾT TOÁN ĐI ÍT BƯỚC HƠN: gửi → kế toán chốt sổ (hoặc trả lại). Không có bước "cấp
	   tiền" vì tiền đã đi từ đời tạm ứng rồi.
	   ⚠️ HAI DANH SÁCH RIÊNG, KHÔNG GỘP LÀM MỘT. Gộp thì `dat_tt_dot()` (tạm ứng) nhận luôn
	      'xong' — một lệnh tạm ứng nhảy thẳng sang "đã chốt sổ" mà chưa ai cấp đồng nào. */
	const TT_QT = array( 'xin', 'xong', 'tra' );

	private static function tt_hop_le_( $loai ) { return ( 'qt' === $loai ) ? self::TT_QT : self::TT_DOT; }

	/* 🔑 MỘT BỘ MÁY, HAI LOẠI LỆNH. 'tu' = tạm ứng (xin tiền trước), 'qt' = quyết toán (chốt
	   sổ sau khi đã chi). Hai loại đi cùng một hình: gom nhiều hạng mục, một số tiền, một
	   trạng thái, một người duyệt. Viết hai bộ mã cho hai loại là hai nơi sẽ lệch nhau — chỗ
	   này chặn xin hai lần, chỗ kia quên chặn. */
	private static function dot_meta_( $loai ) { return ( 'qt' === $loai ) ? 'daQT_' : 'daDot_'; }

	public static function get_dot( $ma_da, $loai = 'tu' ) {
		$o = VHCPVP_Meta::get_json( self::dot_meta_( $loai ) . $ma_da, array() );
		return is_array( $o ) ? $o : array();
	}

	public static function dot_cua( $ma_da, $dot, $loai = 'tu' ) {
		$o = self::get_dot( $ma_da, $loai );
		$k = (string) (int) $dot;
		if ( ! isset( $o[ $k ] ) || ! is_array( $o[ $k ] ) ) { return null; }
		$x = $o[ $k ];
		return array(
			'dot'    => (int) $k,
			/* 🔴 KIỂM THEO ĐÚNG LOẠI LỆNH. Kiểm bằng danh sách của tạm ứng thì trạng thái 'xong'
			   của quyết toán rơi vào nhánh ngã-về — lệnh đã chốt sổ đọc ra thành "chờ chốt",
			   và kế toán chốt bao nhiêu lần cũng thấy nó chưa chốt. */
			'tt'     => isset( $x['tt'] ) && in_array( $x['tt'], self::tt_hop_le_( $loai ), true ) ? $x['tt'] : 'xin',
			'rows'   => isset( $x['rows'] ) && is_array( $x['rows'] ) ? array_values( array_map( 'intval', $x['rows'] ) ) : array(),
			'soTien' => isset( $x['soTien'] ) ? VHCPVP_Util::num( $x['soTien'] ) : 0,
			'unc'    => isset( $x['unc'] ) ? (string) $x['unc'] : '',
			'lyDo'   => isset( $x['lyDo'] ) ? (string) $x['lyDo'] : '',
			'lich'   => isset( $x['lich'] ) && is_array( $x['lich'] ) ? array_values( $x['lich'] ) : array(),
			/* ═══════════════════════════════════════════════════════════════════════════════════
			 * SỔ CÁC LẦN CẤP TIỀN THẬT — khác hẳn `lich` ở ngay trên.
			 * ═══════════════════════════════════════════════════════════════════════════════════
			 * `lich` là KẾ HOẠCH nhân viên khai lúc xin ("ngày 3 lấy 10tr, ngày 10 lấy 20tr") để
			 * kế toán liệu tiền. `daCap` là thứ ĐÃ XẢY RA: mỗi lần kế toán đưa tiền là một dòng,
			 * có số tiền, ngày, uỷ nhiệm chi và tên người cấp.
			 * Trộn hai thứ làm một là không bao giờ trả lời được câu "còn phải đưa bao nhiêu" —
			 * kế hoạch thì luôn đủ, còn tiền thật thì chưa.
			 */
			'daCap'  => isset( $x['daCap'] ) && is_array( $x['daCap'] ) ? array_values( $x['daCap'] ) : array(),
			'moc'    => isset( $x['moc'] ) && is_array( $x['moc'] ) ? $x['moc'] : array(),
		);
	}

	/** Tổng tiền THẬT đã cấp cho một lệnh. */
	public static function da_cap_tong( $d ) {
		$t = 0;
		foreach ( (array) ( isset( $d['daCap'] ) ? $d['daCap'] : array() ) as $x ) {
			$t += VHCPVP_Util::num( isset( $x['soTien'] ) ? $x['soTien'] : 0 );
		}
		return $t;
	}

	/**
	 * Tổng đã cấp GẮN VÀO TỪNG LẦN của lịch — trả về map  lần => số tiền.
	 *
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 CHỈ ĐẾM THỨ ĐÃ GẮN. Anh Thắng 18/09/2026: *"Xin lần 1 thì cấp lần 1 chứ"*.
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * Trước 1.201.0 sổ cấp tiền không biết mình đang trả cho lần nào, nên màn chỉ còn cách ghép
	 * theo THỨ TỰ — và ghép theo thứ tự là BỊA: kế toán đưa 15tr trong khi lần 1 hẹn 10tr, hay
	 * gộp hai lần làm một, thì "lần 1 đã nhận đủ" là một câu không ai kiểm được.
	 * Nay kế toán CHỌN lần lúc cấp, nên con số này là thứ có người khai, không phải suy ra.
	 *
	 * ⚠️ Dòng `choLan = 0` (cấp chung, không gắn lần nào — lối "cấp trọn" cũ và mọi dòng trong
	 *    sổ trước 1.201.0) KHÔNG rơi vào lần nào cả. Nhét đại nó vào lần 1 là dựng lại đúng cái
	 *    ghép bịa vừa bỏ đi.
	 */
	public static function da_cap_theo_lan( $d ) {
		$m = array();
		foreach ( (array) ( isset( $d['daCap'] ) ? $d['daCap'] : array() ) as $x ) {
			$l = (int) ( isset( $x['choLan'] ) ? $x['choLan'] : 0 );
			if ( $l <= 0 ) { continue; }
			if ( ! isset( $m[ $l ] ) ) { $m[ $l ] = 0; }
			$m[ $l ] += VHCPVP_Util::num( isset( $x['soTien'] ) ? $x['soTien'] : 0 );
		}
		return $m;
	}

	/**
	 * Chứng từ SẴN CÓ trên một dòng — ảnh bill trước, rồi tới hồ sơ. '' nếu chưa có gì.
	 *
	 * Cột ẢNH của hàng là chỗ chụp bill, cột HỒ SƠ là bản PDF / hoá đơn điện tử. Hai cột ấy
	 * đứng trước ô "Hoá đơn" của bước chốt về mặt thời gian — người ta đính lúc đi mua về, chứ
	 * không đợi tới lúc chốt. Xem 🔴 ở `dat_hm()`.
	 *
	 * ⚠️ HỒ SƠ CÓ THỂ LÀ NHIỀU TỆP ngăn nhau bằng xuống dòng (`them_ho_so_line`). Lấy tệp ĐẦU —
	 *    nhét cả chùm vào ô hoá đơn thì bản xuất ra một ô dài mấy dòng.
	 */
	private static function chung_tu_san_( $ma_da, $row ) {
		$d = self::get_du_an( $ma_da );
		foreach ( (array) ( isset( $d['lines'] ) ? $d['lines'] : array() ) as $l ) {
			if ( (int) $l['row'] !== (int) $row ) { continue; }
			$anh = trim( (string) ( isset( $l['anh'] ) ? $l['anh'] : '' ) );
			if ( '' !== $anh ) { return $anh; }
			$hs = trim( (string) ( isset( $l['hoSo'] ) ? $l['hoSo'] : '' ) );
			if ( '' !== $hs ) {
				$mot = preg_split( '/[\r\n]+/', $hs );
				return trim( (string) $mot[0] );
			}
		}
		return '';
	}

	/** Số tiền của MỘT lần trong lịch (0 nếu không có lần ấy). */
	public static function tien_lan_lich( $d, $lan ) {
		foreach ( (array) ( isset( $d['lich'] ) ? $d['lich'] : array() ) as $y ) {
			if ( (int) ( isset( $y['lan'] ) ? $y['lan'] : 0 ) === (int) $lan ) {
				return VHCPVP_Util::num( isset( $y['soTien'] ) ? $y['soTien'] : 0 );
			}
		}
		return 0;
	}

	/** Còn phải cấp bao nhiêu nữa cho một lệnh (không bao giờ âm). */
	public static function con_phai_cap( $d ) {
		$con = VHCPVP_Util::num( isset( $d['soTien'] ) ? $d['soTien'] : 0 ) - self::da_cap_tong( $d );
		return $con > 0 ? $con : 0;
	}

	/**
	 * Mọi lệnh của một dự án, đợt nhỏ trước — kèm `soHien`, SỐ ĐỢT NGƯỜI TA NHÌN THẤY.
	 *
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 LỆNH BỊ TRẢ LẠI KHÔNG TÍNH LÀ MỘT ĐỢT. Anh Thắng 17/09/2026: *"khi trả đơn thì phải
	 *    hiểu không tính đó là 1 đợt"*.
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * Trước đây gửi lại sau khi bị trả thì ra "Đợt 2", trong khi thực tế tiền mới đi đúng một
	 * lần. Đọc bảng thành ra dự án này ứng làm hai đợt — mà đợt 1 là một lệnh không tồn tại
	 * nữa. Con số đợt là thứ kế toán dùng để nói chuyện ("cấp tiền đợt mấy rồi"), nên nó đếm
	 * sai là hai bên nói hai chuyện.
	 *
	 * ⚠️ KHOÁ LƯU TRỮ (`dot`) GIỮ NGUYÊN, KHÔNG ĐÁNH SỐ LẠI. Nó là khoá của cả sổ lệnh lẫn cột
	 *    `dot` trên từng hạng mục; đánh số lại là mọi tham chiếu cũ trỏ sang lệnh khác — im
	 *    lặng, và đúng vào chỗ tiền. Chỉ con số HIỆN RA mới đếm lại.
	 *
	 * ⚠️ `soHien = 0` nghĩa là "không tính" — màn hình in một dấu gạch kèm chữ đã trả, chứ
	 *    không giấu dòng đi. Giấu là mất dấu vết ai trả, trả lúc nào, vì sao; mà dòng ấy còn
	 *    mang cả mốc thời gian của lượt xin và lượt duyệt trước đó.
	 *
	 * ⚠️ Lệnh đã trả là TẬN CÙNG — `dat_tt_dot()` chỉ cho 'tra' từ 'xin'/'duyet' và không có
	 *    đường nào quay lại 'xin'. Nên số hiện của mấy lệnh sau nó không bao giờ nhảy lại.
	 */
	public static function ds_dot( $ma_da, $loai = 'tu' ) {
		$ra = array();
		foreach ( array_keys( self::get_dot( $ma_da, $loai ) ) as $k ) {
			$d = self::dot_cua( $ma_da, $k, $loai );
			if ( $d ) { $ra[] = $d; }
		}
		usort( $ra, function ( $a, $b ) { return $a['dot'] - $b['dot']; } );
		$dem = 0;
		foreach ( $ra as $i => $d ) {
			if ( 'tra' === $d['tt'] ) { $ra[ $i ]['soHien'] = 0; continue; }
			$dem++;
			$ra[ $i ]['soHien'] = $dem;
		}
		return $ra;
	}

	/** Bản đồ `số đợt trong sổ` -> `số đợt hiện ra`. Màn hình nào in số đợt cũng dịch qua đây. */
	public static function ban_do_so_dot( $ma_da, $loai = 'tu' ) {
		$m = array();
		foreach ( self::ds_dot( $ma_da, $loai ) as $d ) { $m[ (string) $d['dot'] ] = (int) $d['soHien']; }
		return $m;
	}

	private static function dot_ghi_( $ma_da, $dot, $sua, $viec, $loai = 'tu' ) {
		$o  = self::get_dot( $ma_da, $loai );
		$k  = (string) (int) $dot;
		$cu = self::dot_cua( $ma_da, $dot, $loai );
		$moi = array_merge( $cu ? $cu : array( 'dot' => (int) $dot, 'tt' => 'xin', 'rows' => array(),
			'soTien' => 0, 'unc' => '', 'lyDo' => '', 'lich' => array(), 'moc' => array() ), $sua );
		$moi['moc'] = ( $cu && $cu['moc'] ) ? $cu['moc'] : array();
		$moi['moc'][ $moi['tt'] ] = VHCPVP_Util::now()->format( 'd/m/Y H:i' ) . ' · ' . VHCPVP_Auth::nguoi();
		$o[ $k ] = $moi;
		VHCPVP_Meta::set_json( self::dot_meta_( $loai ) . $ma_da, $o );
		VHCPVP_Log::log_action( array(
			'actor'  => VHCPVP_Auth::nguoi(),
			'role'   => VHCPVP_Auth::vai_tro(),
			'action' => (string) $viec,
			'target' => (string) $ma_da . ' · lệnh ' . $k,
			'detail' => 'trạng thái ' . $moi['tt'] . ' · ' . count( $moi['rows'] ) . ' hạng mục · '
				. number_format( (float) $moi['soTien'], 0, ',', '.' ) . 'đ'
				. ( '' !== $moi['unc'] ? ( ' · UNC ' . $moi['unc'] ) : '' )
				. ( '' !== $moi['lyDo'] ? ( ' — ' . $moi['lyDo'] ) : '' ),
		) );
		return $moi;
	}

	/**
	 * Tiền của MỘT hạng mục lớn — hạng mục có con thì tiền nằm ở CON, không có con thì ở chính
	 * nó. Cộng cả hai là đếm hai lần, và đó là con số kế toán chuẩn bị tiền.
	 */
	public static function tien_hm( $ma_da, $row ) {
		$lines = self::lines_of( $ma_da );
		$nd = ''; $tu_than = 0;
		foreach ( $lines as $l ) {
			if ( (int) $l['row_no'] === (int) $row ) {
				$nd = trim( (string) $l['noi_dung'] );
				$tu_than = VHCPVP_Util::num( $l['thuc_te'] );
			}
		}
		if ( '' === $nd ) { return 0; }
		$con = 0; $co_con = false;
		foreach ( $lines as $l ) {
			if ( trim( (string) $l['cap_cha'] ) === $nd ) { $co_con = true; $con += VHCPVP_Util::num( $l['thuc_te'] ); }
		}
		return $co_con ? $con : $tu_than;
	}

	/**
	 * TIỀN DỰ KIẾN CỦA MỘT HẠNG MỤC — dùng cho mọi con số đứng TRƯỚC lúc tiền ra khỏi két.
	 *
	 * =============================================================================================
	 * 🔴 VÌ SAO PHẢI CÓ HÀM THỨ HAI — 17/09/2026, anh Thắng: *"đã nhập số liệu thì cần cộng vào
	 *    luôn để biết bao nhiêu"*, kèm ảnh một dự án có 125.907.312đ dự toán mà thẻ
	 *    *"Dự kiến tạm ứng tổng đơn"* đứng **0đ**, và *"Số tiền đã xin tạm ứng"* cũng **0đ** dù
	 *    dòng ghi chú ngay cạnh nói *"✓ đã gửi xin hết"*.
	 * =============================================================================================
	 * `tien_hm()` chỉ đọc cột `thuc_te`. Điều ấy ĐÚNG cho quyết toán — quyết toán là đối chiếu
	 * tiền đã tiêu thật. Nhưng nó SAI cho hai chỗ đứng trước đó:
	 *
	 *   · thẻ "Dự kiến tạm ứng tổng đơn" — chú thích của chính nó ghi *"để kế toán liệu tiền
	 *     TRƯỚC khi nhân viên bấm xin"*. Trước khi xin thì chưa tiêu đồng nào, nên nó bằng 0
	 *     đúng vào lúc người ta cần nó nhất.
	 *   · `xin_tam_ung_dot()` — SỐ TIỀN CỦA LỆNH XIN TẠM ỨNG. Đây mới là chỗ đắt: nhân viên
	 *     tích đủ hạng mục, bấm xin, và hệ thống dựng một lệnh xin **0đ**. Không câu lỗi nào,
	 *     màn hình vẫn báo đã gửi. Kế toán mở ra thấy một lệnh rỗng, còn nhân viên thì tưởng
	 *     mình đã xin xong 125 triệu. Đó chính là hai con số 0 trong ảnh.
	 *
	 * 🔴 BA CỘT TIỀN, KHÔNG PHẢI HAI — chỗ này suýt sửa hụt. Một dòng chi phí có `thuc_te`,
	 *    `du_toan` VÀ `thanh_tien` (= số lượng × đơn giá, xem `line_data()`). Đơn **chi phí cơ
	 *    sở** không dùng ô Dự toán chút nào: nhân viên gõ số lượng và đơn giá, tiền nằm trọn ở
	 *    `thanh_tien`. Bản đầu của hàm này chỉ nhìn `thuc_te` + `du_toan`, nên nó vá xong đơn dự
	 *    án mà đơn cơ sở VẪN xin 0đ — cùng một lỗi, ở cột thứ ba.
	 *    `kiem-don-coso-tu-quyet-toan.php` bắt được ngay, và đó là lý do bài ấy đáng giá.
	 *
	 * LUẬT: lấy số ĐÃ BIẾT TỐT NHẤT — thực tế → dự toán → thành tiền.
	 *   · Hạng mục có mục con: tổng thực tế của con; con chưa nhập gì thì lùi về số của cha.
	 *   · Hạng mục không con: thực tế của chính nó, rồi dự toán, rồi thành tiền.
	 *   Dự toán đứng TRƯỚC thành tiền vì ở màn dự án nó là ô người ta cố ý gõ "xin bấy nhiêu",
	 *   còn thành tiền là số máy tự nhân ra. Một dòng hiếm khi có cả hai — đơn cơ sở chỉ có
	 *   thành tiền, đơn dự án chỉ có dự toán — nên thứ tự này phục vụ đủ cả hai mà không phải đoán.
	 *
	 * ⚠️ HÀM RIÊNG, KHÔNG SỬA `tien_hm()`. Hàm ấy còn hai nơi gọi nữa — `gui_quyet_toan()` và
	 *    đường quyết toán tự động — và cả hai PHẢI giữ nguyên "chỉ thực tế". Cho quyết toán lùi
	 *    về dự toán là chốt sổ bằng con số kế hoạch khi chưa ai nhập tiền thật: sổ khớp đẹp,
	 *    tiền thì không ai biết đã đi đâu. Đây đúng là loại hỏng im lặng mà đổi một hàm dùng
	 *    chung sẽ gây ra, nên tách làm hai cái tên.
	 *
	 * ⚠️ 0 KHÔNG PHẢI "CHƯA NHẬP" theo nghĩa tuyệt đối — một hạng mục thực chi đúng 0đ là có
	 *    thật (hàng được tặng). Nhưng nó lùi về dự toán thì cũng chỉ ra đúng con số kế hoạch,
	 *    và người dùng thấy nó trên màn để sửa. Ngược lại — báo 0 cho một khoản 48 triệu — thì
	 *    không có gì trên màn hình chỉ ra là sai.
	 */
	public static function tien_hm_du_kien( $ma_da, $row ) {
		$lines = self::lines_of( $ma_da );
		$nd = ''; $tu_than = 0; $ke_hoach = 0;
		foreach ( $lines as $l ) {
			if ( (int) $l['row_no'] === (int) $row ) {
				$nd      = trim( (string) $l['noi_dung'] );
				$tu_than = VHCPVP_Util::num( $l['thuc_te'] );
				$dt      = VHCPVP_Util::num( $l['du_toan'] );
				$tht     = VHCPVP_Util::num( $l['thanh_tien'] );
				$ke_hoach = $dt > 0 ? $dt : $tht;
			}
		}
		if ( '' === $nd ) { return 0; }
		$con = 0; $co_con = false;
		foreach ( $lines as $l ) {
			if ( trim( (string) $l['cap_cha'] ) === $nd ) { $co_con = true; $con += VHCPVP_Util::num( $l['thuc_te'] ); }
		}
		if ( $co_con ) { return $con > 0 ? $con : $ke_hoach; }
		return $tu_than > 0 ? $tu_than : $ke_hoach;
	}

	/**
	 * NHÂN VIÊN TÍCH MẤY HẠNG MỤC → MỘT LỆNH TẠM ỨNG.
	 *
	 * 🔴 CHỈ NHẬN HẠNG MỤC LỚN ĐANG Ở 'nhap' HOẶC 'tra'. Hạng mục đã nằm trong một lệnh khác mà
	 *    lọt vào lệnh này là XIN HAI LẦN CÙNG MỘT KHOẢN — tiền ra khỏi két gấp đôi cho một việc.
	 *
	 * 🔴 KHÔNG NHẬN HẠNG MỤC 🏢 TRỰC TIẾP. Tiền ấy kế toán trả thẳng nhà cung cấp, không qua tay
	 *    nhân viên; gộp nó vào lệnh tạm ứng là xin tiền cho một khoản mình không trả.
	 */
	public static function xin_tam_ung_dot( $ma_da, $rows, $lich = array(), $ghi_chu = '' ) {
		if ( ! self::find( $ma_da ) ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$rows = array_values( array_unique( array_map( 'intval', (array) $rows ) ) );
		if ( ! $rows ) { return VHCPVP_Util::err( 'Chưa tích hạng mục nào để xin tạm ứng.' ); }

		/* Hạng mục lớn của dự án này, tra một lần — hỏi CSDL trong vòng lặp là mở cửa cho
		   dòng của dự án khác lọt vào lệnh. */
		$lon = array();
		foreach ( self::lines_of( $ma_da ) as $l ) {
			if ( ! self::is_real( $l ) ) { continue; }
			if ( trim( (string) $l['cap_cha'] ) !== '' ) { continue; }
			$lon[ (int) $l['row_no'] ] = trim( (string) $l['noi_dung'] );
		}

		$nhan = array(); $tong = 0;
		foreach ( $rows as $r ) {
			if ( ! isset( $lon[ $r ] ) ) {
				return VHCPVP_Util::err( 'Dòng ' . $r . ' không phải hạng mục lớn của dự án này.' );
			}
			if ( self::hm_la_ncc( $ma_da, $r ) ) {
				return VHCPVP_Util::err( '"' . $lon[ $r ] . '" là khoản kế toán trả thẳng nhà cung cấp — không xin tạm ứng.' );
			}
			$h = self::hm_cua( $ma_da, $r );
			if ( ! in_array( $h['tt'], array( 'nhap', 'tra' ), true ) ) {
				return VHCPVP_Util::err( '"' . $lon[ $r ] . '" đã nằm trong một lệnh tạm ứng rồi.' );
			}
			$nhan[] = $r;
			/* ⚠️ `tien_hm_du_kien`, KHÔNG phải `tien_hm`. Đây là SỐ TIỀN CỦA LỆNH XIN TẠM ỨNG —
			   tiền chưa tiêu, nên hỏi cột thực tế thì được đúng số 0. Xem khối 🔴 ở
			   `tien_hm_du_kien()`; đây là chỗ đắt nhất của lỗi ấy. */
			$tong  += self::tien_hm_du_kien( $ma_da, $r );
		}

		/* 🔴 KHÔNG DỰNG LỆNH XIN 0đ. 17/09/2026 — đây là cái đã xảy ra thật: mọi hạng mục mới
		   chỉ có dự toán, `tien_hm()` đọc cột thực tế nên `$tong` ra 0, và hệ thống vẫn dựng
		   một lệnh xin tạm ứng rỗng rồi báo "đã gửi". Nhân viên tưởng đã xin xong 125 triệu;
		   kế toán mở ra thấy một lệnh không đồng nào; không bên nào có gì trên màn hình để nghi.
		   Nay `tien_hm_du_kien()` lùi về dự toán nên ca ấy hết. Chốt này là LƯỚI CUỐI cho những
		   ca còn lại — hạng mục chưa gõ cả dự toán lẫn thực tế — và nó nói thẳng phải làm gì. */
		if ( $tong <= 0 ) {
			return VHCPVP_Util::err( 'Mấy hạng mục vừa tích đều chưa có số tiền nào — Dự toán, '
				. 'Số lượng × Đơn giá và Chi phí thực tế đều trống hoặc 0. Điền số vào rồi xin '
				. 'tạm ứng; gửi một lệnh 0đ thì kế toán không có gì để cấp.' );
		}

		$ds  = self::get_dot( $ma_da );
		$dot = 0;
		foreach ( array_keys( $ds ) as $k ) { $dot = max( $dot, (int) $k ); }
		$dot++;

		/* ═════════════════════════════════════════════════════════════════════════════════════
		 * LỊCH NHẬN TIỀN = TỪNG ĐỢT GÁN LẤY MẤY HẠNG MỤC, TIỀN DO MÁY CHỦ CỘNG.
		 * ═════════════════════════════════════════════════════════════════════════════════════
		 * Anh Thắng 17/09/2026: *"Anh muốn xác định chi phí từng hàng là chi lần 1, hay chi lần 2"*.
		 *
		 * Bản trước cho gõ TAY số tiền của từng lần. Hai cái giá đã trả:
		 *   · Số ấy không dính gì tới hàng nào cả — không ai trả lời được "lần 1 gồm những gì".
		 *   · Và nó lệch được với tổng lệnh: đơn của anh khai 10tr + 20tr cho một lệnh
		 *     68.790.000đ, còn 38.790.000đ không thuộc lần nào mà chẳng có gì nói ra.
		 *
		 * Nay mỗi đợt mang DANH SÁCH HÀNG (`rows`), và `soTien` do đây cộng từ chính mấy hàng ấy.
		 *
		 * 🔴 KHÔNG NHẬN `soTien` TỪ MÀN. Nhận là mở đường cho hai con số: danh sách hàng nói một
		 *    đằng, số tiền nói một nẻo, và không ai biết bên nào đúng. Máy chủ cộng thì con số ấy
		 *    KHÔNG THỂ lệch với danh sách — đó là cả điểm của việc này.
		 * 🔴 CỘNG BẰNG `tien_hm_du_kien()`, đúng hàm dựng nên `$tong` của cả lệnh ở trên. Dùng
		 *    hàm khác là tổng các đợt không bao giờ khớp tổng lệnh.
		 *
		 * ⚠️ MỘT HÀNG KHÔNG ĐƯỢC NẰM TRONG HAI ĐỢT — cùng một khoản mà hẹn nhận hai lần thì tổng
		 *    các đợt vượt tổng lệnh, và kế toán chuẩn bị thừa tiền.
		 * ⚠️ HÀNG KHÔNG THUỘC LỆNH NÀY THÌ CHỐI, không lặng lẽ bỏ: người dùng thấy nó trong danh
		 *    sách lúc gửi, mà lệnh lại không có nó.
		 * ⚠️ Dòng thiếu NGÀY vẫn bỏ như cũ — một đợt không ngày thì kế toán chuẩn bị tiền hôm nào?
		 * ⚠️ Hàng KHÔNG gán vào đợt nào là chuyện bình thường: đó là "dự kiến đợt tiếp theo"
		 *    (xem `get_du_an`), không phải lỗi.
		 */
		$lc = array(); $lan = 0; $da_gan = array();
		$trong_lenh = array_fill_keys( $nhan, 1 );
		foreach ( (array) $lich as $x ) {
			$x    = (array) $x;
			$ngay = isset( $x['ngay'] ) ? trim( (string) $x['ngay'] ) : '';
			if ( '' === $ngay ) { continue; }
			$rows_dot = array(); $tien_dot = 0;
			foreach ( (array) ( isset( $x['rows'] ) ? $x['rows'] : array() ) as $rr ) {
				$rr = (int) $rr;
				if ( ! isset( $trong_lenh[ $rr ] ) ) {
					return VHCPVP_Util::err( 'Dòng ' . $rr . ' được xếp vào một lần nhận tiền nhưng '
						. 'không nằm trong lệnh này.' );
				}
				if ( isset( $da_gan[ $rr ] ) ) {
					return VHCPVP_Util::err( '"' . $lon[ $rr ] . '" bị xếp vào hai lần nhận tiền — '
						. 'một khoản chỉ nhận một lần.' );
				}
				$da_gan[ $rr ] = 1;
				$rows_dot[]    = $rr;
				$tien_dot     += self::tien_hm_du_kien( $ma_da, $rr );
			}
			$lan++;
			$lc[] = array( 'lan' => $lan, 'ngay' => $ngay, 'soTien' => $tien_dot, 'rows' => $rows_dot );
		}

		$moi = self::dot_ghi_( $ma_da, $dot, array(
			'tt' => 'xin', 'rows' => $nhan, 'soTien' => $tong, 'lich' => $lc,
			'lyDo' => trim( (string) $ghi_chu ), 'unc' => '',
		), 'Xin tạm ứng cho dự án' );
		foreach ( $nhan as $r ) { self::hm_ghi_( $ma_da, $r, array( 'tt' => 'xin', 'dot' => $dot ) ); }
		/* Câu báo trên màn phải nói ĐÚNG con số mà bảng ngay dưới nó sắp in ra — lệnh bị trả
		   không tính là một đợt (🔴 ở `ds_dot()`). Gửi kèm `soHien` thay vì để màn tự đoán. */
		$__bd = self::ban_do_so_dot( $ma_da, 'tu' );
		$moi['soHien'] = isset( $__bd[ (string) $dot ] ) ? (int) $__bd[ (string) $dot ] : 0;
		return VHCPVP_Util::ok( array( 'dot' => $moi, 'soTien' => $tong, 'so' => count( $nhan ) ) );
	}

	/**
	 * Đổi trạng thái CẢ LỆNH — duyệt / trả lại / cấp tiền. Mọi hạng mục trong lệnh đi theo.
	 *
	 * 🔴 CHỐT THEO VAI. Ẩn nút trên màn chỉ là tiện tay; ai gọi thẳng API vẫn phải bị chặn,
	 *    nếu không thì nhân viên tự duyệt rồi tự cấp tạm ứng cho chính mình.
	 */
	/**
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * CẤP TIỀN LÀM NHIỀU LẦN CHO MỘT LỆNH.
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 10/09/2026: *"nếu 1 lần mà đi tạm ứng nhiều lần thì nv có thể lịch chọn ngày đi
	 * tạm ứng lần 1,2,3"*; rồi 17/09/2026 hỏi thẳng *"nếu xin nhiều lần và cấp nhiều lần thì
	 * sao"*. Phần HẸN LỊCH đã có từ lâu (`lich`); phần GHI NHẬN TỪNG LẦN NHẬN thì chưa — cấp
	 * tiền vốn là một cú lật duy nhất, cấp là cấp trọn lệnh.
	 *
	 * Hệ quả của việc thiếu nó: lệnh 48 triệu mà kế toán mới đưa 20 triệu thì không có chỗ nào
	 * ghi lại. Muốn biết "còn phải đưa bao nhiêu" thì hỏi mồm — đúng thứ hệ này sinh ra để bỏ.
	 *
	 * 🔴 KHÔNG THÊM TRẠNG THÁI MỚI CHO LỆNH. Thêm 'đang cấp' vào `TT_DOT` là nó chảy xuống trạng
	 *    thái của TỪNG HẠNG MỤC (`dat_tt_dot` đẩy `tt` của lệnh xuống mọi hàng), rồi vào
	 *    `hm_du_tu_qt()`, vào bảng Duyệt, vào mọi phép so `'ung' === $h['tt']`. Một trạng thái
	 *    mới là một nhánh chưa ai đi trong hàng chục phép so — và chúng hỏng im lặng.
	 *    Nên: lệnh vẫn ở 'duyet' cho tới khi cấp ĐỦ, rồi mới sang 'ung'. Phần "đang cấp dở" nằm
	 *    ở SỐ TIỀN (`daCap`), không nằm ở trạng thái. Màn hình đọc số ấy mà nói "đã cấp 20/48".
	 *
	 * 🔴 CẤP ĐỦ THÌ ĐI ĐÚNG ĐƯỜNG CŨ. Lượt cấp cuối gọi thẳng `dat_tt_dot(...,'ung')`, nên mọi
	 *    thứ móc vào lượt ấy — hạng mục sang 'ung', `tu_quyet_toan_coso()` của đơn cơ sở, mốc
	 *    thời gian, nhật ký — chạy y như trước. Chép lại một bản rút gọn ở đây là hai đường cấp
	 *    tiền, và sớm muộn một đường quên một việc.
	 *
	 * ⚠️ KHÔNG CHO CẤP QUÁ SỐ CỦA LỆNH. Đưa dư là tiền ra khỏi két nhiều hơn số đã duyệt, mà
	 *    lệnh thì chỉ được duyệt tới đấy. Cần đưa thêm thì xin một lệnh mới — ở đó có người duyệt.
	 * ⚠️ 0đ HAY SỐ ÂM THÌ CHỐI. Một dòng cấp 0đ chỉ làm sổ dài ra mà không nói gì.
	 */
	public static function cap_tien_phan( $ma_da, $dot, $them = array() ) {
		if ( ! self::find( $ma_da ) ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$d = self::dot_cua( $ma_da, $dot );
		if ( ! $d ) { return VHCPVP_Util::err( 'Không tìm thấy lệnh tạm ứng ' . (int) $dot ); }
		$vai = VHCPVP_Auth::vai_tro();
		if ( ! in_array( $vai, array( 'Admin', 'Kế toán cá nhân', 'Kế toán NCC' ), true ) ) {
			return VHCPVP_Util::err( 'Chỉ kế toán cấp tạm ứng được.' );
		}
		if ( 'ung' === $d['tt'] ) { return VHCPVP_Util::err( 'Lệnh này đã cấp đủ tiền rồi.' ); }
		if ( 'duyet' !== $d['tt'] ) {
			return VHCPVP_Util::err( 'Chỉ cấp tiền cho lệnh ĐÃ DUYỆT. Lệnh này đang ở bước "'
				. $d['tt'] . '".' );
		}

		$them   = (array) $them;
		$so     = VHCPVP_Util::num( isset( $them['soTien'] ) ? $them['soTien'] : 0 );
		$con    = self::con_phai_cap( $d );
		if ( $so <= 0 ) {
			return VHCPVP_Util::err( 'Nhập số tiền thật sự đưa lần này (lớn hơn 0).' );
		}
		if ( $so > $con ) {
			return VHCPVP_Util::err( 'Lệnh này chỉ còn ' . number_format( (float) $con, 0, ',', '.' )
				. 'đ chưa cấp, không đưa được ' . number_format( (float) $so, 0, ',', '.' ) . 'đ. '
				. 'Cần đưa thêm thì nhân viên xin một lệnh mới — lệnh mới có người duyệt.' );
		}

		/* 🔴 KHÔNG CHỌN NGÀY THÌ LẤY ĐÚNG LÚC BẤM — KÈM GIỜ. Anh Thắng 18/09/2026: *"Nếu kế toán
		   bấm cấp tiền mà không chọn ngày thì tự hiểu là lấy ngày bấm cấp làm ngày cấp tiền
		   (kèm giờ luôn cho đầy đủ)"*, kèm ảnh sổ "Đã cấp" có ba dòng mà hai dòng trống ngày.
		   Ô ngày là tuỳ chọn, và đường cấp TRỌN MỘT LẦN (`dat_tt_dot('ung')`) còn chẳng có ô
		   nào — nên bỏ trống là ca thường, không phải ca hiếm. Để trống thì sổ chi tiền mất mốc
		   thời gian, mà đối chiếu ngân hàng thì mốc ấy là thứ đầu tiên người ta dò.
		   ⚠️ GHI VÀO `ngay` chứ không chỉ vá lúc hiển thị: ai đọc sổ qua đường khác (xuất MISA,
		      tra lịch sử) cũng phải thấy cùng một ngày, không phải mỗi màn hình mới có. */
		/* ─────────────────────────────────────────────────────────────────────────────────────
		 * 🔴 CẤP CHO LẦN NÀO — KẾ TOÁN KHAI, KHÔNG SUY RA.
		 *
		 * Anh Thắng 18/09/2026: *"Xin lần 1 thì cấp lần 1 chứ"*. Nhân viên đã xếp từng hạng mục
		 * vào lần 1 / lần 2 lúc xin; lúc đưa tiền mà không nói đưa cho lần nào thì cả cái xếp ấy
		 * thành vô nghĩa — sổ chỉ biết "đã đưa tổng bao nhiêu".
		 *
		 * ⚠️ CHỐI SỐ VƯỢT PHẦN CỦA LẦN ẤY. Lần 1 hẹn 10tr mà gắn 20tr vào nó thì lần 2 vĩnh viễn
		 *    trông như chưa nhận, trong khi tiền đã ra. Câu chối nói cả số còn lại của lần ấy —
		 *    kế toán sửa được ngay tại chỗ, khỏi đi tra.
		 * ⚠️ `choLan = 0` LÀ HỢP LỆ: lệnh không khai lịch (nhận một lần) thì chẳng có lần nào để
		 *    gắn, và ép gắn là bịa ra một cái lịch không ai lập.
		 * ─────────────────────────────────────────────────────────────────────────────────────── */
		$cho_lan = (int) ( isset( $them['choLan'] ) ? $them['choLan'] : 0 );
		if ( $cho_lan > 0 ) {
			$tien_lan = self::tien_lan_lich( $d, $cho_lan );
			if ( $tien_lan <= 0 ) {
				return VHCPVP_Util::err( 'Lệnh này không có lần nhận tiền số ' . $cho_lan . '.' );
			}
			$da_lan  = self::da_cap_theo_lan( $d );
			$con_lan = $tien_lan - ( isset( $da_lan[ $cho_lan ] ) ? $da_lan[ $cho_lan ] : 0 );
			if ( $so > $con_lan ) {
				return VHCPVP_Util::err( 'Lần ' . $cho_lan . ' chỉ còn '
					. number_format( (float) $con_lan, 0, ',', '.' ) . 'đ chưa nhận, không gắn '
					. number_format( (float) $so, 0, ',', '.' ) . 'đ vào đó được. '
					. 'Đưa thêm cho lần khác thì chọn đúng lần ấy.' );
			}
		}

		$luc   = VHCPVP_Util::now()->format( 'd/m/Y H:i' );
		$ngay  = trim( (string) ( isset( $them['ngay'] ) ? $them['ngay'] : '' ) );
		$ghi   = $d['daCap'];
		$ghi[] = array(
			'lan'    => count( $ghi ) + 1,
			'choLan' => $cho_lan,
			'soTien' => $so,
			'ngay'   => ( '' !== $ngay ? $ngay : $luc ),
			'unc'    => trim( (string) ( isset( $them['unc'] ) ? $them['unc'] : '' ) ),
			'nguoi'  => VHCPVP_Auth::nguoi(),
			'luc'    => $luc,
		);
		$het = ( $so >= $con );   // lượt này trả nốt phần còn lại

		if ( $het ) {
			/* Ghi sổ TRƯỚC rồi mới lật trạng thái: `dat_tt_dot` đọc lại lệnh từ sổ, nên ghi sau
			   là lượt cấp cuối biến mất khỏi sổ. */
			self::dot_ghi_( $ma_da, $dot, array( 'daCap' => $ghi ), 'Cấp tạm ứng (lần cuối) cho dự án' );
			$kq = self::dat_tt_dot( $ma_da, $dot, 'ung',
				isset( $them['unc'] ) ? array( 'unc' => trim( (string) $them['unc'] ) ) : array() );
			if ( empty( $kq['success'] ) ) { return $kq; }
			$moi = self::dot_cua( $ma_da, $dot );
			return VHCPVP_Util::ok( array( 'dot' => $moi, 'daCap' => self::da_cap_tong( $moi ),
				'con' => 0, 'xong' => true,
				'tuQuyetToan' => isset( $kq['tuQuyetToan'] ) ? (int) $kq['tuQuyetToan'] : 0 ) );
		}

		$moi = self::dot_ghi_( $ma_da, $dot, array( 'daCap' => $ghi ), 'Cấp tạm ứng từng phần cho dự án' );
		$moi = self::dot_cua( $ma_da, $dot );
		return VHCPVP_Util::ok( array( 'dot' => $moi, 'daCap' => self::da_cap_tong( $moi ),
			'con' => self::con_phai_cap( $moi ), 'xong' => false ) );
	}

	public static function dat_tt_dot( $ma_da, $dot, $tt, $them = array() ) {
		if ( ! self::find( $ma_da ) ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$tt = (string) $tt;
		if ( ! in_array( $tt, self::TT_DOT, true ) ) { return VHCPVP_Util::err( 'Trạng thái không hợp lệ' ); }
		$d = self::dot_cua( $ma_da, $dot );
		if ( ! $d ) { return VHCPVP_Util::err( 'Không tìm thấy lệnh tạm ứng ' . (int) $dot ); }
		$them = (array) $them;
		$vai  = VHCPVP_Auth::vai_tro();
		$duyet_duoc = in_array( $vai, array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' ), true );
		$ke_toan    = in_array( $vai, array( 'Admin', 'Kế toán cá nhân', 'Kế toán NCC' ), true );

		if ( in_array( $tt, array( 'duyet', 'tra' ), true ) && ! $duyet_duoc ) {
			return VHCPVP_Util::err( 'Chỉ quản lý hoặc kế toán duyệt / trả lại được.' );
		}
		if ( 'ung' === $tt && ! $ke_toan ) { return VHCPVP_Util::err( 'Chỉ kế toán cấp tạm ứng được.' ); }
		/* ═════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 ADMIN THU HỒI ĐƯỢC MỘT LỆNH ĐÃ CẤP TIỀN — 18/09/2026.
		 *
		 * Anh Thắng: *"Cấp quyền cho admin trả đơn"*, sau khi một đơn 68.790.000đ đã cấp tiền mà
		 * số liệu sai và không còn đường nào quay lại.
		 *
		 * Luật cũ chặn tuyệt đối ("tiền đã ra khỏi két, trả lại là xoá dấu vết") — đúng về ý,
		 * sai về hệ quả: một hệ không có đường lùi thì người ta không sửa sổ, họ BỊA sổ. Thà mở
		 * một cửa hẹp có tên, có lý do, có vết, còn hơn để họ gõ đè lên mấy con số cũ.
		 *
		 * ⚠️ CHỈ ADMIN. Quản lý và kế toán vẫn chỉ trả được lệnh chưa cấp tiền — đây là việc gỡ
		 *    một khoản đã ra khỏi két, không phải việc thường ngày.
		 * ⚠️ BẮT BUỘC CÓ LÝ DO. Đây là chỗ duy nhất trong hệ gỡ ngược một khoản tiền thật; không
		 *    ghi vì sao thì ba tháng sau không ai dựng lại được chuyện gì đã xảy ra.
		 * ⚠️ MẤY LƯỢT CẤP TIỀN VẪN NẰM NGUYÊN TRONG SỔ CỦA LỆNH — không xoá dòng nào. Cái đổi là
		 *    lệnh 'tra' thôi được cộng vào "đã chi" / "còn treo trên TK 141" (xem `get_du_an`),
		 *    đúng lựa chọn anh Thắng chốt: *"coi như chưa chi"*.
		 * ⚠️ CHỐI NẾU TRONG LỆNH ĐÃ CÓ HẠNG MỤC CHỐT XONG hoặc đã gửi quyết toán. Gỡ tạm ứng
		 *    dưới chân một khoản đã quyết toán là sổ 141 âm mà không ai hiểu vì sao — phải mở
		 *    khoá / trả lệnh quyết toán ấy trước.
		 * ═════════════════════════════════════════════════════════════════════════════════════ */
		if ( 'ung' === $d['tt'] ) {
			if ( 'tra' !== $tt ) { return VHCPVP_Util::err( 'Lệnh ' . $d['dot'] . ' đã cấp tiền rồi.' ); }
			if ( 'Admin' !== $vai ) {
				return VHCPVP_Util::err( 'Lệnh ' . $d['dot'] . ' đã cấp tiền rồi — chỉ Admin thu hồi được.' );
			}
			if ( '' === trim( (string) ( isset( $them['lyDo'] ) ? $them['lyDo'] : '' ) ) ) {
				return VHCPVP_Util::err( 'Thu hồi một lệnh ĐÃ CẤP TIỀN thì phải ghi lý do — '
					. 'đây là chỗ duy nhất gỡ ngược một khoản tiền thật.' );
			}
			foreach ( $d['rows'] as $r ) {
				$h = self::hm_cua( $ma_da, $r );
				if ( 'xong' === $h['tt'] || (int) $h['qtDot'] > 0 ) {
					return VHCPVP_Util::err( 'Trong lệnh này có hạng mục đã chốt xong'
						. ( (int) $h['qtDot'] > 0 ? ( ' / đã gửi quyết toán lệnh ' . (int) $h['qtDot'] ) : '' )
						. '. Mở khoá (hoặc trả lệnh quyết toán) cho hạng mục ấy trước, '
						. 'rồi mới thu hồi lệnh tạm ứng — không thì sổ 141 âm mà không ai hiểu vì sao.' );
				}
			}
		}
		if ( 'duyet' === $tt && 'xin' !== $d['tt'] ) { return VHCPVP_Util::err( 'Chỉ duyệt được lệnh đang xin tạm ứng.' ); }
		if ( 'ung' === $tt && 'duyet' !== $d['tt'] ) { return VHCPVP_Util::err( 'Chỉ cấp tiền cho lệnh đã duyệt.' ); }
		if ( 'tra' === $tt && ! in_array( $d['tt'], array( 'xin', 'duyet', 'ung' ), true ) ) {
			return VHCPVP_Util::err( 'Lệnh này không ở bước trả lại được.' );
		}

		$sua = array( 'tt' => $tt );
		if ( isset( $them['unc'] ) ) { $sua['unc'] = trim( (string) $them['unc'] ); }
		if ( isset( $them['lyDo'] ) ) { $sua['lyDo'] = trim( (string) $them['lyDo'] ); }
		/* 🔴 CẤP TRỌN MỘT LẦN CŨNG PHẢI VÀO SỔ CÁC LẦN CẤP. Nếu chỉ `cap_tien_phan()` ghi sổ thì
		   sổ ấy thủng đúng ở lối đang được dùng nhiều nhất, và con số "đã chi" đọc theo nó sẽ
		   thiếu mất phần lớn tiền. Ghi nốt phần CÒN LẠI — lượt cấp cuối của đường từng phần đã
		   ghi dòng của nó rồi, nên ở đây `con` bằng 0 và không sinh dòng thừa. */
		if ( 'ung' === $tt ) {
			$con_lai = self::con_phai_cap( $d );
			if ( $con_lai > 0 ) {
				$luc_c   = VHCPVP_Util::now()->format( 'd/m/Y H:i' );
				$ghi_c   = $d['daCap'];
				/* Lối này đưa NỐT phần còn lại, không hỏi cho lần nào. Gắn được đúng một ca:
				   lịch chỉ có MỘT lần và nó còn thiếu — lúc ấy không có gì để nhầm. Lịch nhiều
				   lần thì một lượt đưa này trả cho nhiều lần cùng lúc, gắn vào một lần là bịa. */
				$cho_c    = 0;
				$lich_c   = isset( $d['lich'] ) ? (array) $d['lich'] : array();
				if ( 1 === count( $lich_c ) ) {
					$l1     = (int) ( isset( $lich_c[0]['lan'] ) ? $lich_c[0]['lan'] : 0 );
					$da_c   = self::da_cap_theo_lan( $d );
					$con_c  = self::tien_lan_lich( $d, $l1 ) - ( isset( $da_c[ $l1 ] ) ? $da_c[ $l1 ] : 0 );
					if ( $l1 > 0 && $con_lai <= $con_c ) { $cho_c = $l1; }
				}
				$ghi_c[] = array(
					'lan'    => count( $ghi_c ) + 1,
					'choLan' => $cho_c,
					'soTien' => $con_lai,
					/* Đường này KHÔNG có ô ngày nào để kế toán chọn, nên mốc duy nhất đúng là
					   lúc bấm. Xem khối 🔴 ở `cap_tien_phan()`. */
					'ngay'   => $luc_c,
					'unc'    => isset( $sua['unc'] ) ? $sua['unc'] : $d['unc'],
					'nguoi'  => VHCPVP_Auth::nguoi(),
					'luc'    => $luc_c,
				);
				$sua['daCap'] = $ghi_c;
			}
		}
		$viec = array( 'duyet' => 'Duyệt lệnh tạm ứng dự án', 'tra' => 'Trả lại lệnh tạm ứng dự án',
			'ung' => 'Cấp tạm ứng cho dự án', 'xin' => 'Xin tạm ứng cho dự án' );
		$moi = self::dot_ghi_( $ma_da, $dot, $sua, isset( $viec[ $tt ] ) ? $viec[ $tt ] : 'Đổi lệnh tạm ứng' );

		/* Hạng mục đi theo lệnh. TRẢ LẠI thì về 'nhap' và GỠ số đợt — nếu giữ đợt thì nhân viên
		   sửa xong tích lại sẽ bị chối "đã nằm trong một lệnh rồi", mà lệnh ấy đã bị trả. */
		foreach ( $d['rows'] as $r ) {
			$h = self::hm_cua( $ma_da, $r );
			if ( 'xong' === $h['tt'] ) { continue; }   // đã chốt hoá đơn thì thôi, không kéo ngược
			if ( 'tra' === $tt ) { self::hm_ghi_( $ma_da, $r, array( 'tt' => 'tra', 'dot' => 0 ) ); }
			else { self::hm_ghi_( $ma_da, $r, array( 'tt' => $tt, 'dot' => (int) $d['dot'],
				'unc' => isset( $sua['unc'] ) ? $sua['unc'] : $h['unc'] ) ); }
		}

		/* 🔴 MÓC 1/2 CỦA LUẬT "ĐƠN CƠ SỞ TỰ QUYẾT TOÁN": vừa CẤP TIỀN xong. Hạng mục nào đã đủ
		   hoá đơn từ trước thì chốt luôn. Xem khối 🔴 ở `tu_quyet_toan_coso()`. */
		$tu_qt = array( 'so' => 0 );
		if ( 'ung' === $tt ) { $tu_qt = self::tu_quyet_toan_coso( $ma_da, $d['rows'] ); }
		return VHCPVP_Util::ok( array( 'dot' => $moi, 'tuQuyetToan' => (int) $tu_qt['so'] ) );
	}

	/**
	 * NHÂN VIÊN TÍCH MẤY HẠNG MỤC ĐÃ CHỐT → MỘT LỆNH QUYẾT TOÁN.
	 *
	 * 🔴 Anh Thắng: *"khi đơn này đã xong, tích chọn để gửi quyết toán theo đơn"*.
	 *
	 *    Chốt hoàn thành ("xong") mới chỉ nói: hạng mục này đã có hoá đơn, số tiền là chi thực
	 *    tế. Quyết toán là bước sau đó — đối chiếu tiền đã ứng với tiền đã chi để ra thừa /
	 *    thiếu, rồi kế toán chốt sổ. Không có bước này thì hạng mục nằm mãi ở "đã chốt" và
	 *    khoản tạm ứng treo trên TK 141 không ai tất toán.
	 *
	 * 🔴 CHỈ NHẬN HẠNG MỤC ĐÃ CHỐT XONG. Gửi quyết toán cho một hạng mục chưa có hoá đơn là
	 *    chốt sổ một con số không có gì đỡ.
	 *
	 * 🔴 MỘT HẠNG MỤC KHÔNG NẰM TRONG HAI LỆNH QUYẾT TOÁN. Quyết toán hai lần cùng một khoản là
	 *    tất toán gấp đôi số đã ứng — sổ 141 âm mà không ai hiểu vì sao.
	 */
	public static function xin_quyet_toan_dot( $ma_da, $rows, $ghi_chu = '' ) {
		if ( ! self::find( $ma_da ) ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$rows = array_values( array_unique( array_map( 'intval', (array) $rows ) ) );
		if ( ! $rows ) { return VHCPVP_Util::err( 'Chưa tích hạng mục nào để gửi quyết toán.' ); }

		$lon = array();
		foreach ( self::lines_of( $ma_da ) as $l ) {
			if ( ! self::is_real( $l ) ) { continue; }
			if ( trim( (string) $l['cap_cha'] ) !== '' ) { continue; }
			$lon[ (int) $l['row_no'] ] = trim( (string) $l['noi_dung'] );
		}

		$nhan = array(); $tong = 0;
		foreach ( $rows as $r ) {
			if ( ! isset( $lon[ $r ] ) ) {
				return VHCPVP_Util::err( 'Dòng ' . $r . ' không phải hạng mục lớn của dự án này.' );
			}
			$h = self::hm_cua( $ma_da, $r );
			if ( 'xong' !== $h['tt'] ) {
				return VHCPVP_Util::err( '"' . $lon[ $r ] . '" chưa chốt hoàn thành — chốt hoá đơn xong mới gửi quyết toán được.' );
			}
			if ( $h['qtDot'] > 0 ) {
				return VHCPVP_Util::err( '"' . $lon[ $r ] . '" đã nằm trong một lệnh quyết toán rồi.' );
			}
			$nhan[] = $r;
			$tong  += self::tien_hm( $ma_da, $r );
		}

		$ds  = self::get_dot( $ma_da, 'qt' );
		$dot = 0;
		foreach ( array_keys( $ds ) as $k ) { $dot = max( $dot, (int) $k ); }
		$dot++;

		$moi = self::dot_ghi_( $ma_da, $dot, array(
			'tt' => 'xin', 'rows' => $nhan, 'soTien' => $tong, 'lich' => array(),
			'lyDo' => trim( (string) $ghi_chu ), 'unc' => '',
		), 'Gửi quyết toán dự án', 'qt' );
		foreach ( $nhan as $r ) { self::hm_ghi_( $ma_da, $r, array( 'qtDot' => $dot ) ); }
		/* Cùng lý do với lệnh tạm ứng: câu báo phải nói đúng con số bảng sắp in. */
		$__bd = self::ban_do_so_dot( $ma_da, 'qt' );
		$moi['soHien'] = isset( $__bd[ (string) $dot ] ) ? (int) $__bd[ (string) $dot ] : 0;
		return VHCPVP_Util::ok( array( 'dot' => $moi, 'soTien' => $tong, 'so' => count( $nhan ) ) );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * ĐƠN CHI PHÍ CƠ SỞ TỰ QUYẾT TOÁN — THANH TOÁN MỘT LẦN.
	 *
	 * Anh Thắng 11/09/2026: *"Đơn chi phí cơ sở là đơn thanh toán 1 lần, khi cấp tiền và đủ hóa
	 * đơn, cấp xong nó tự đẩy sang đã quyết toán luôn"*.
	 *
	 * 🔴 HAI ĐIỀU KIỆN, VÀ CÁI NÀO XONG SAU THÌ CÁI ĐÓ ĐẨY.
	 *    Thứ tự thật ngoài đời không cố định: có tuần kế toán cấp tiền trước rồi nhân viên mới
	 *    về đính hoá đơn; có tuần nhân viên đính hoá đơn từ hôm trước, hôm sau kế toán mới
	 *    chuyển khoản. Chỉ đẩy ở một nhánh thì nửa số đơn nằm treo mãi ở "chờ chốt", mà nhìn
	 *    màn không có gì nói vì sao. Nên móc ở CẢ HAI chỗ, và phép kiểm giống hệt nhau —
	 *    `tu_quyet_toan_coso()` là nơi DUY NHẤT biết luật.
	 *
	 * 🔴 CHỈ ĐƠN CHI PHÍ CƠ SỞ. Dự án Setup/Tháo dỡ tạm ứng nhiều đợt, chi rải nhiều tháng, và
	 *    quyết toán là lúc kế toán ngồi đối chiếu cả dự án — tự chốt sổ hộ ở đó là cướp mất
	 *    bước đối chiếu của người giữ sổ 141.
	 *
	 * ⚠️ KHÔNG ĐỘNG VÀO HẠNG MỤC ĐÃ NẰM TRONG MỘT LỆNH QUYẾT TOÁN (`qtDot > 0`). Quyết toán hai
	 *    lần cùng một khoản là tất toán gấp đôi số đã ứng — sổ 141 âm mà không ai hiểu vì sao.
	 *
	 * ⚠️ KHÔNG GỌI `dat_tt_qt()`. Hàm ấy gác quyền "chỉ kế toán chốt sổ" — đúng cho người bấm
	 *    tay, nhưng ở đây chính kế toán vừa cấp tiền, hoặc nhân viên vừa đính nốt hoá đơn cho
	 *    một khoản kế toán đã cấp. Đi qua cửa ấy là nhân viên đính hoá đơn xong nhận một câu
	 *    chối khó hiểu. Ghi thẳng, và nói rõ trong nhật ký rằng MÁY tự chốt.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */

	/** Hạng mục đã đủ điều kiện tự quyết toán chưa: thuộc một lệnh tạm ứng · đã chốt hoá đơn · chưa quyết toán. */
	private static function hm_du_tu_qt( $h ) {
		if ( ! $h ) { return false; }
		if ( (int) $h['qtDot'] > 0 ) { return false; }
		if ( 'xong' !== $h['tt'] ) { return false; }
		/* `dot > 0` = thuộc một lệnh tạm ứng. Lệnh ấy đã cấp tiền chưa thì phải hỏi chính lệnh:
		   trạng thái hạng mục đã nhảy sang 'xong' rồi, nó không còn nhớ mình từng ở 'ung' hay
		   chưa — mà "đã chốt hoá đơn" với "đã nhận tiền" là hai chuyện khác hẳn nhau.

		   ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG, ghi lại để lần sau khỏi đuổi theo: đổi dòng này thành `true`
		      KHÔNG đổi kết quả — hạng mục không thuộc lệnh nào có `dot = 0`, mà bảng `$da_cap`
		      chỉ chứa số đợt CÓ THẬT nên `$da_cap[0]` luôn rỗng và chỗ gọi chặn tiếp. Giữ vế
		      này vì nó nói thẳng ra điều kiện, và vì ngày nào bảng `$da_cap` đổi cách dựng thì
		      đây là lớp chặn còn lại cho đơn 🏢 trực tiếp (kế toán trả thẳng NCC, không có lệnh
		      tạm ứng nào — không có khoản ứng nào để tất toán). */
		return (int) $h['dot'] > 0;
	}

	/**
	 * Tự lập lệnh quyết toán và chốt luôn cho những hạng mục đã đủ điều kiện.
	 *
	 * ⚠️ LUÔN PHẢI TRUYỀN `$rows`. Từng có nhánh "rỗng = xét cả đơn" cho tiện, nhưng không lối
	 *    gọi thật nào dùng tới — mà một nhánh không ai đi qua thì không bài kiểm nào giữ nó
	 *    đúng, và nó vẫn nằm đó chờ người sau tin là chạy được. Cần rà cả đơn thì chỗ gọi tự
	 *    liệt kê hạng mục lớn ra, ở đó mới biết mình đang rà cái gì.
	 *
	 * @param string $ma_da Mã dự án.
	 * @param array  $rows  Hạng mục cần xét.
	 * @return array so · dot · soTien.
	 */
	public static function tu_quyet_toan_coso( $ma_da, $rows ) {
		$f = self::find( $ma_da );
		if ( ! $f || ! self::la_don_coso( $f['loai'] ) ) { return array( 'so' => 0, 'dot' => 0 ); }

		$lon = array();
		foreach ( self::lines_of( $ma_da ) as $l ) {
			if ( ! self::is_real( $l ) ) { continue; }
			if ( trim( (string) $l['cap_cha'] ) !== '' ) { continue; }
			$lon[ (int) $l['row_no'] ] = trim( (string) $l['noi_dung'] );
		}
		$xet = array();
		foreach ( (array) $rows as $r ) { if ( isset( $lon[ (int) $r ] ) ) { $xet[] = (int) $r; } }
		/* ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG, ghi lại để lần sau khỏi đuổi theo: bỏ dòng này KHÔNG đổi kết
		   quả — vòng dưới chạy trên mảng rỗng nên `$nhan` rỗng, và `if ( ! $nhan )` trả về đúng
		   thế. Giữ vì nó cắt hẳn một lượt hỏi CSDL (`get_dot`) cho lời gọi chắc chắn không có
		   gì để làm — mà lời gọi ấy nằm trên đường cấp tiền, chỗ người dùng đang đứng đợi. */
		if ( ! $xet ) { return array( 'so' => 0, 'dot' => 0 ); }

		/* Lệnh tạm ứng nào ĐÃ CẤP TIỀN — tra một lượt, đừng hỏi trong vòng lặp. */
		$da_cap = array();
		foreach ( self::get_dot( $ma_da, 'tu' ) as $k => $d ) {
			if ( 'ung' === ( isset( $d['tt'] ) ? $d['tt'] : '' ) ) { $da_cap[ (int) $k ] = 1; }
		}

		$nhan = array(); $tong = 0;
		foreach ( array_unique( $xet ) as $r ) {
			$h = self::hm_cua( $ma_da, $r );
			if ( ! self::hm_du_tu_qt( $h ) ) { continue; }
			if ( empty( $da_cap[ (int) $h['dot'] ] ) ) { continue; }   // lệnh chưa cấp tiền
			$nhan[] = (int) $r;
			$tong  += self::tien_hm( $ma_da, $r );
		}
		if ( ! $nhan ) { return array( 'so' => 0, 'dot' => 0 ); }

		$ds  = self::get_dot( $ma_da, 'qt' );
		$dot = 0;
		foreach ( array_keys( $ds ) as $k ) { $dot = max( $dot, (int) $k ); }
		$dot++;

		/* Lập lệnh rồi CHỐT luôn — hai bước tay gộp thành một nhịp máy. Ghi thẳng 'xong' chứ
		   không 'xin' rồi sửa: hai lượt ghi là hai dòng nhật ký cho một việc, và cái dòng giữa
		   ("đang xin quyết toán") không tồn tại thật một giây nào. */
		$moi = self::dot_ghi_( $ma_da, $dot, array(
			'tt' => 'xong', 'rows' => $nhan, 'soTien' => $tong, 'lich' => array(),
			'lyDo' => 'Tự chốt: đơn chi phí cơ sở thanh toán một lần — đã cấp tiền và đủ hoá đơn.',
			'unc'  => '',
		), 'Tự chốt quyết toán đơn chi phí cơ sở', 'qt' );
		foreach ( $nhan as $r ) { self::hm_ghi_( $ma_da, $r, array( 'qtDot' => $dot ) ); }
		return array( 'so' => count( $nhan ), 'dot' => $moi, 'soTien' => $tong );
	}

	/**
	 * Kế toán CHỐT hoặc TRẢ LẠI một lệnh quyết toán.
	 *
	 * 🔴 CHỐT SỔ LÀ VIỆC CỦA KẾ TOÁN. Nhân viên tự chốt là tự nói "khoản của tôi đã đối chiếu
	 *    xong" — mà đối chiếu là việc của người giữ sổ 141.
	 */
	public static function dat_tt_qt( $ma_da, $dot, $tt, $them = array() ) {
		if ( ! self::find( $ma_da ) ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$tt = (string) $tt;
		if ( ! in_array( $tt, array( 'xong', 'tra' ), true ) ) { return VHCPVP_Util::err( 'Trạng thái không hợp lệ' ); }
		$d = self::dot_cua( $ma_da, $dot, 'qt' );
		if ( ! $d ) { return VHCPVP_Util::err( 'Không tìm thấy lệnh quyết toán ' . (int) $dot ); }
		$vai = VHCPVP_Auth::vai_tro();
		if ( ! in_array( $vai, array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' ), true ) ) {
			return VHCPVP_Util::err( 'Chỉ quản lý hoặc kế toán chốt / trả lại quyết toán được.' );
		}
		if ( 'xong' === $d['tt'] ) { return VHCPVP_Util::err( 'Lệnh quyết toán ' . $d['dot'] . ' đã chốt sổ rồi.' ); }

		$sua = array( 'tt' => $tt );
		if ( isset( $them['lyDo'] ) ) { $sua['lyDo'] = trim( (string) $them['lyDo'] ); }
		$moi = self::dot_ghi_( $ma_da, $dot, $sua,
			'tra' === $tt ? 'Trả lại lệnh quyết toán dự án' : 'Chốt quyết toán dự án', 'qt' );

		/* TRẢ LẠI thì GỠ cờ quyết toán khỏi hạng mục — giữ lại thì nhân viên sửa xong tích lại
		   sẽ bị chối "đã nằm trong một lệnh rồi", mà lệnh ấy đã bị trả. */
		if ( 'tra' === $tt ) {
			foreach ( $d['rows'] as $r ) { self::hm_ghi_( $ma_da, $r, array( 'qtDot' => 0 ) ); }
		}
		return VHCPVP_Util::ok( array( 'dot' => $moi ) );
	}

	/**
	 * MỌI LỆNH TẠM ỨNG CỦA MỌI DỰ ÁN — nuôi bảng Duyệt của kế toán.
	 *
	 * ⚠️ Tôn trọng tầm nhìn đơn vị như `list_du_an()`: dự án neo theo NGƯỜI TẠO.
	 */
	public static function list_lenh_da( $loai = 'tu' ) {
		$loai   = ( 'qt' === $loai ) ? 'qt' : 'tu';
		$out    = array();
		$dv_xem = VHCPVP_DonVi::xem_duoc();
		foreach ( self::all_with_lines() as $r ) {
			if ( null !== $dv_xem
				&& ! VHCPVP_DonVi::duoc_xem( VHCPVP_DonVi::cua_nguoi( isset( $r['nguoi_tao'] ) ? $r['nguoi_tao'] : '' ) ) ) { continue; }
			$ma_da = (string) $r['ma_da'];
			$ten_cua = array();
			foreach ( $r['lines'] as $x ) { $ten_cua[ (int) $x['row_no'] ] = trim( (string) $x['noi_dung'] ); }
			foreach ( self::ds_dot( $ma_da, $loai ) as $d ) {
				$ten = array();
				foreach ( $d['rows'] as $rw ) { if ( isset( $ten_cua[ $rw ] ) ) { $ten[] = $ten_cua[ $rw ]; } }
				$out[] = array(
					'loai'     => $loai,
					'maDA'     => $ma_da,
					'tenDA'    => (string) $r['ten'],
					'loaiDA'   => (string) $r['loai'],
					'isCoSo'   => self::la_don_coso( $r['loai'] ),
					'nguoiTao' => isset( $r['nguoi_tao'] ) ? (string) $r['nguoi_tao'] : '',
					'dot'      => $d['dot'],
					/* Số đợt HIỆN RA — lệnh bị trả không tính là một đợt (🔴 ở `ds_dot()`). */
					'soHien'   => isset( $d['soHien'] ) ? (int) $d['soHien'] : 0,
					'tt'       => $d['tt'],
					'rows'     => $d['rows'],
					'tenHM'    => $ten,
					'soTien'   => $d['soTien'],
					/* 🔴 PHẢI GỬI KÈM SỔ CẤP TIỀN. Thiếu nó thì màn Duyệt đọc "đã đưa" ra 0 với
					   MỌI lệnh, nên ô "Số tiền đưa lần này" điền sẵn TRỌN số lệnh kể cả lúc đã
					   đưa một phần — bấm lần hai là mời chuyển đi lần nữa. Máy chủ chặn được
					   (`cap_tien_phan` chối khi vượt phần còn lại), nhưng lúc ấy màn đã nói dối
					   rồi, và kế toán đọc con số chứ không đọc mã nguồn.
					   Anh Thắng 18/09/2026: *"bấm cấp lần 2,3 là số tiền còn lại hoặc thấp hơn
					   chứ"*. Trang dự án vốn đúng (nó gửi trọn `$d`); chỉ màn Duyệt lọc bớt. */
					'daCap'    => $d['daCap'],
					'unc'      => $d['unc'],
					'lyDo'     => $d['lyDo'],
					'lich'     => $d['lich'],
					'moc'      => $d['moc'],
					'kyDA'     => self::get_ky_da( $ma_da ),
				);
			}
		}
		return VHCPVP_Util::ok( array( 'items' => $out ) );
	}

	/**
	 * Đổi trạng thái một hạng mục.
	 *
	 * 🔴 CHỐT THEO VAI, KHÔNG THEO NÚT TRÊN MÀN. Màn ẩn nút là tiện tay; ai gọi thẳng API vẫn
	 *    phải bị chặn — nếu không thì nhân viên tự duyệt và tự cấp tạm ứng cho chính mình.
	 */
	public static function dat_hm( $ma_da, $row, $tt, $them = array() ) {
		if ( ! self::find( $ma_da ) ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$tt = (string) $tt;
		if ( ! in_array( $tt, self::TT_HM, true ) ) { return VHCPVP_Util::err( 'Trạng thái không hợp lệ' ); }
		$them = (array) $them;
		$vai  = VHCPVP_Auth::vai_tro();
		$duyet_duoc = in_array( $vai, array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' ), true );
		$ke_toan    = in_array( $vai, array( 'Admin', 'Kế toán cá nhân', 'Kế toán NCC' ), true );
		$cu = self::hm_cua( $ma_da, $row );
		/* 🏢 ĐƠN KẾ TOÁN TRẢ THẲNG NCC ĐI ĐƯỜNG RIÊNG. Anh Thắng 10/09/2026: *"dù không xin
		   tạm ứng, nhưng vẫn có phần kế toán đã xác nhận đi đơn nào thì tích vào và khoá đơn
		   đó cho nhân viên biết và kèm gửi uỷ nhiệm chi cho đơn đó thay vì nhân viên gửi
		   (người gửi lỡ người kia quên)"*.
		   Tiền của đơn này không qua tay nhân viên nên chuỗi xin → duyệt → cấp không có nghĩa:
		   nhân viên chẳng xin gì cả. Kế toán tự tích khi đã chi, đính uỷ nhiệm chi, rồi khoá.
		   ⚠️ CHỈ NỚI CHO KẾ TOÁN. Nới cho mọi vai thì nhân viên tự khoá đơn của chính mình,
		      mà khoá là chốt "đây là chi thực tế" — câu ấy phải do người giữ két nói. */
		$la_ncc = self::hm_la_ncc( $ma_da, $row );

		/* Đã khoá thì mọi đường đều đóng, trừ lối mở lại của kế toán. */
		if ( 'xong' === $cu['tt'] && 'nhap' !== $tt ) {
			return VHCPVP_Util::err( 'Hạng mục đã chốt là chi thực tế — không đổi được nữa.' );
		}
		if ( 'xong' === $cu['tt'] && 'nhap' === $tt && ! $ke_toan ) {
			return VHCPVP_Util::err( 'Chỉ kế toán mở lại được hạng mục đã chốt.' );
		}
		if ( in_array( $tt, array( 'duyet', 'tra' ), true ) && ! $duyet_duoc ) {
			return VHCPVP_Util::err( 'Chỉ quản lý hoặc kế toán duyệt / trả lại được.' );
		}
		if ( 'ung' === $tt && ! $ke_toan ) {
			return VHCPVP_Util::err( 'Chỉ kế toán cấp tạm ứng được.' );
		}
		/* 🔴 ĐƠN NCC THÌ CHỈ KẾ TOÁN ĐƯỢC ĐÁNH DẤU ĐÃ CHI / KHOÁ. Với đơn tạm ứng, nhân viên tự
		   tích hoàn thành là đúng ý anh Thắng (*"tích hoàn thành và bổ sung hoá đơn nó sẽ khoá
		   đơn đó lại"*) — họ là người cầm tiền đi mua. Đơn NCC thì ngược hẳn: tiền do kế toán
		   trả, nên chỉ kế toán mới biết đã đi hay chưa. Để nhân viên khoá là họ chốt hộ một
		   khoản chính họ không trả, mà từ 'nhap' đi thẳng tới 'xong' là qua mặt cả chuỗi. */
		if ( $la_ncc && in_array( $tt, array( 'xong', 'ung' ), true ) && ! $ke_toan ) {
			return VHCPVP_Util::err( 'Hạng mục này kế toán trả thẳng nhà cung cấp — chỉ kế toán xác nhận đã chi và khoá đơn.' );
		}
		if ( $la_ncc && 'xin' === $tt ) {
			return VHCPVP_Util::err( 'Hạng mục này kế toán trả thẳng nhà cung cấp — không xin tạm ứng. Kế toán tích xác nhận đã chi.' );
		}
		/* 🔴 XIN / DUYỆT / CẤP TIỀN CỦA ĐƠN TẠM ỨNG NAY ĐI THEO LỆNH, KHÔNG THEO TỪNG HẠNG MỤC.
		   Anh Thắng: *"Trong 1 đơn chứ, trong 1 đơn mà nhiều lệnh tạm ứng, đơn nào bấm xin thì
		   nó tổng tổng tạm ứng cần xin"*.
		   Để hở đường cũ là mở ra một lối thứ hai: hạng mục nhảy sang 'xin' mà KHÔNG thuộc lệnh
		   nào, nên bảng Duyệt của kế toán không bao giờ thấy nó — nhân viên tưởng đã gửi, kế
		   toán không có gì để duyệt, và không ai biết vì sao.
		   ⚠️ Đơn 🏢 TRỰC TIẾP thì vẫn đi lối riêng: nó không có lệnh tạm ứng nào cả. */
		if ( ! $la_ncc && in_array( $tt, array( 'xin', 'duyet', 'ung' ), true ) ) {
			return VHCPVP_Util::err( 'Bước này nay đi theo LỆNH tạm ứng của cả dự án — '
				. 'tích các hạng mục rồi bấm "Xin tạm ứng" một lần.' );
		}
		/* Đơn NCC: kế toán đi thẳng tới "đã chi" hoặc "khoá", không cần chuỗi xin → duyệt. */
		$bo_qua_chuoi = ( $la_ncc && $ke_toan );
		if ( ! $bo_qua_chuoi ) {
			if ( 'xin' === $tt && ! in_array( $cu['tt'], array( 'nhap', 'tra' ), true ) ) {
				return VHCPVP_Util::err( 'Hạng mục này đã qua bước xin tạm ứng rồi.' );
			}
			if ( 'duyet' === $tt && 'xin' !== $cu['tt'] ) {
				return VHCPVP_Util::err( 'Chỉ duyệt được hạng mục đang xin tạm ứng.' );
			}
			if ( 'ung' === $tt && 'duyet' !== $cu['tt'] ) {
				return VHCPVP_Util::err( 'Chỉ cấp tạm ứng cho hạng mục đã duyệt.' );
			}
		}
		/* 🔴 XONG PHẢI CÓ HOÁ ĐƠN. Anh Thắng: *"tích hoàn thành và bổ sung hóa đơn nó sẽ khóa
		   đơn đó lại"*. Khoá mà chưa có chứng từ là chốt một con số không có gì đỡ. */
		$hd = isset( $them['hoaDon'] ) ? trim( (string) $them['hoaDon'] ) : $cu['hoaDon'];
		if ( 'xong' === $tt ) {
			/* 🏢 ĐƠN NCC: MỘT CHỨNG TỪ LÀ ĐỦ. Anh Thắng: *"chỗ này nhập tối thiểu 1 ảnh là
			   được"*. Kế toán trả thẳng nhà cung cấp thì có khi cầm về uỷ nhiệm chi trước, hoá
			   đơn nhà cung cấp xuất sau vài hôm — bắt đủ cả hai là đơn nằm treo dù tiền đã đi
			   và đã có chứng từ chuyển khoản.
			   ⚠️ ĐƠN TẠM ỨNG THÌ VẪN BẮT HOÁ ĐƠN. Nhân viên cầm tiền đi mua, thứ chứng minh
			      khoản chi là hoá đơn — uỷ nhiệm chi ở đó chỉ nói kế toán đã đưa tiền cho họ,
			      không nói họ đã tiêu vào đâu. */
			$unc_moi = isset( $them['unc'] ) ? trim( (string) $them['unc'] ) : $cu['unc'];
			if ( $la_ncc ) {
				if ( '' === $hd && '' === $unc_moi ) {
					return VHCPVP_Util::err( 'Phải đính ít nhất một chứng từ (uỷ nhiệm chi hoặc hoá đơn) trước khi khoá đơn.' );
				}
			} elseif ( '' === $hd ) {
				/* ═══════════════════════════════════════════════════════════════════════════════
				 * 🔴 BILL ĐÃ ĐÍNH TRÊN HÀNG CHÍNH LÀ HOÁ ĐƠN — ĐỪNG BẮT TẢI LÊN LẦN THỨ HAI.
				 *
				 * Anh Thắng 18/09/2026, ảnh một hàng đã có ảnh bill ở cột ẢNH mà bấm Chốt xong
				 * vẫn hiện "Hoá đơn (bắt buộc)": *"Chỗ ảnh đã add hóa đơn, tạo sao chốt lại hỏi
				 * hóa đơn lần 2, nó là 1 mà"*.
				 *
				 * Đúng vậy. Cột ẢNH của hàng vốn là chỗ chụp bill (chính nó mang `data-bill` để
				 * rê chuột phóng to), cột HỒ SƠ là bản PDF / hoá đơn điện tử. Bắt tải lại đúng
				 * cái tệp ấy vào một ô thứ hai là làm hai lần một việc, và tệ hơn: người ta sẽ
				 * dán bừa một thứ khác cho qua cửa — cửa vẫn đóng, chứng từ thì sai.
				 *
				 * ⚠️ VẪN CHỐI KHI HÀNG TRỐNG TRƠN. Chốt là khoá lại và tính thành chi thực tế;
				 *    không có mảnh chứng từ nào thì vẫn không chốt được. Chỉ khác chỗ: nay hỏi
				 *    "có chứng từ nào chưa", không hỏi "có đúng cái ô này chưa".
				 * ⚠️ GHI LẠI VÀO SỔ chứ không chỉ cho qua: lệnh quyết toán và bản xuất đọc ô
				 *    `hoaDon`, để trống thì chốt xong mà sổ vẫn trắng chứng từ. Gán vào
				 *    `$them['hoaDon']` là để mở cổng `isset()` ở dưới — con số ghi xuống là `$hd`.
				 * ═══════════════════════════════════════════════════════════════════════════════ */
				$san = self::chung_tu_san_( $ma_da, $row );
				if ( '' === $san ) {
					return VHCPVP_Util::err( 'Phải đính hoá đơn trước khi chốt hoàn thành — '
						. 'đính ảnh bill hoặc hồ sơ ngay trên hàng cũng được.' );
				}
				$hd             = $san;
				$them['hoaDon'] = $san;
			}
			/* ═══════════════════════════════════════════════════════════════════════════════════
			 * 🔴 CHỐT HOÀN THÀNH MÀ SỐ TIỀN LÀ 0 THÌ CHỐT CÁI GÌ — 17/09/2026.
			 * ═══════════════════════════════════════════════════════════════════════════════════
			 * 'xong' nghĩa là *"đây là chi thực tế"*. Bản trước chỉ đòi có hoá đơn, không đòi có
			 * SỐ TIỀN. Nên chốt được một hạng mục 48 triệu với thực tế bằng 0, rồi đem đi quyết
			 * toán — `xin_quyet_toan_dot()` và `tu_quyet_toan_coso()` đều cộng bằng `tien_hm()`,
			 * tức đúng cột thực tế ấy. Lệnh quyết toán ra 0đ, khoản tạm ứng 48 triệu vẫn treo
			 * nguyên trên TK 141, và sổ thì trông như đã tất toán.
			 *
			 * Đây là cùng một họ với lỗi lệnh xin 0đ vừa vá hôm nay, chỉ khác đầu kia của chuỗi:
			 * một đầu xin 0đ, một đầu quyết toán 0đ. Vá một đầu mà bỏ đầu kia là còn nguyên nửa.
			 *
			 * ⚠️ ĐO BẰNG `tien_hm()`, KHÔNG PHẢI `tien_hm_du_kien()`. Phải đúng con số sẽ đi vào
			 *    lệnh quyết toán; hỏi bản "dự kiến" thì một hạng mục mới có dự toán cũng lọt, và
			 *    lệnh quyết toán vẫn ra 0đ — chốt thành ra không canh gì cả.
			 * ⚠️ Hạng mục có mục con thì `tien_hm()` lấy tổng con, nên câu chối phải nói cả hai
			 *    đường sửa; người đọc không nhớ luật "tiền nằm ở con".
			 * ⚠️ Màn web tự điền thực tế = thành tiền khi ô ấy trống, nên đơn cơ sở nhập bình
			 *    thường không vướng chốt này. Nó chặn đúng mấy đường ghi KHÔNG qua màn. */
			if ( self::tien_hm( $ma_da, $row ) <= 0 ) {
				return VHCPVP_Util::err( 'Hạng mục này chưa có số tiền thực tế nào (đang là 0đ) — '
					. 'chốt hoàn thành thì lệnh quyết toán cũng ra 0đ và khoản tạm ứng vẫn treo '
					. 'nguyên. Điền ô "Chi phí thực tế" cho hạng mục (hoặc cho các mục con của nó) '
					. 'rồi chốt lại.' );
			}
		}

		$sua = array( 'tt' => $tt );
		if ( 'ung' === $tt ) {
			$sua['dot'] = isset( $them['dot'] ) ? max( 1, (int) $them['dot'] ) : ( $cu['dot'] > 0 ? $cu['dot'] : 1 );
			if ( isset( $them['unc'] ) ) { $sua['unc'] = trim( (string) $them['unc'] ); }
		}
		if ( isset( $them['hoaDon'] ) ) { $sua['hoaDon'] = $hd; }
		/* Uỷ nhiệm chi đi kèm cả lúc KHOÁ, không riêng lúc cấp tạm ứng: đơn NCC chỉ có một
		   bước duy nhất (kế toán tích đã chi + khoá), mà uỷ nhiệm chi chính là chứng từ ấy. */
		if ( 'xong' === $tt && isset( $them['unc'] ) ) { $sua['unc'] = trim( (string) $them['unc'] ); }
		/* Lịch đợt: chỉ nhận lúc XIN (đó là lúc nhân viên biết mình cần tiền hôm nào). Dòng
		   thiếu ngày thì bỏ — một đợt không ngày thì kế toán chuẩn bị tiền vào hôm nào? */
		if ( 'xin' === $tt && isset( $them['lich'] ) && is_array( $them['lich'] ) ) {
			$ds = array(); $lan = 0;
			foreach ( $them['lich'] as $x ) {
				$x = (array) $x;
				$ngay = isset( $x['ngay'] ) ? trim( (string) $x['ngay'] ) : '';
				if ( '' === $ngay ) { continue; }
				$lan++;
				$ds[] = array(
					'lan'    => $lan,
					'ngay'   => $ngay,
					'soTien' => VHCPVP_Util::num( isset( $x['soTien'] ) ? $x['soTien'] : 0 ),
				);
			}
			$sua['lich'] = $ds;
		}
		if ( 'nhap' === $tt ) { $sua['dot'] = 0; }
		$moi = self::hm_ghi_( $ma_da, $row, $sua );

		/* 🔴 MÓC 2/2 CỦA LUẬT "ĐƠN CƠ SỞ TỰ QUYẾT TOÁN": vừa CHỐT HOÁ ĐƠN xong. Nếu khoản này
		   đã được cấp tiền từ trước thì chốt quyết toán luôn. Xem khối 🔴 ở
		   `tu_quyet_toan_coso()` — nó tự bỏ qua dự án Setup/Tháo dỡ và hạng mục chưa đủ điều
		   kiện, nên gọi ở đây là an toàn cho mọi loại đơn. */
		$tu_qt = array( 'so' => 0 );
		if ( 'xong' === $tt ) { $tu_qt = self::tu_quyet_toan_coso( $ma_da, array( $row ) ); }
		return VHCPVP_Util::ok( array( 'hm' => $moi, 'tuQuyetToan' => (int) $tu_qt['so'] ) );
	}

	// ---------------------------------------------------------------- dòng hạng mục

	private static function line_data( $rec ) {
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? $rec[ $k ] : null; };
		$sl  = VHCPVP_Util::num( $g( 'soLuong' ) );
		$dg  = VHCPVP_Util::num( $g( 'donGia' ) );
		$cap = VHCPVP_Util::st( $g( 'capCha' ) );
		// Gắn mã tài khoản theo LOẠI CHI PHÍ ngay lúc nhập (giống sổ chi phí).
		$loai_cp = VHCPVP_Util::st( $g( 'loaiCp' ) );
		// Gian hàng đóng vai "cơ sở" ở mảng kỹ thuật -> mã theo mảng kinh doanh của gian đó.
		$tk      = VHCPVP_Cfg::resolve_tk( $loai_cp, VHCPVP_Util::st( $g( 'hinhThuc' ) ), array( 'tkNo' => VHCPVP_Util::st( $g( 'tkNo' ) ), 'tkCo' => VHCPVP_Util::st( $g( 'tkCo' ) ), 'maDt' => VHCPVP_Util::st( $g( 'maDt' ) ) ), VHCPVP_Util::st( $g( 'gian' ) ) );
		return array(
			'loai_cp'    => $loai_cp,
			'tk_no'      => $loai_cp !== '' ? $tk['tk_no'] : '',
			'tk_co'      => $loai_cp !== '' ? $tk['tk_co'] : '',
			'ma_dt'      => $loai_cp !== '' ? $tk['ma_dt'] : '',
			'noi_dung'   => VHCPVP_Util::st( $g( 'noiDung' ) ),
			'du_toan'    => ( $cap === '' ) ? VHCPVP_Util::num( $g( 'duToan' ) ) : 0,   // chỉ hạng mục lớn có dự toán
			/* 🔴 THỰC TẾ TRỐNG -> LẤY THÀNH TIỀN (SL × ĐƠN GIÁ). Chuyển luật này TỪ MÀN HÌNH
			   XUỐNG MÁY CHỦ, 17/09/2026.
			   Màn web vẫn tự điền (`app.html`: *"thực tế trống -> = thành tiền"*), nhưng đó là
			   luật của MỘT đường ghi. Mọi đường khác — cửa API, nạp tệp, một bản app.html cũ
			   trong bộ nhớ đệm — đều ghi thực tế bằng 0 trong khi tiền nằm ở `thanh_tien`. Hàng
			   ấy trông có tiền trên bảng mà `tien_hm()` đọc ra 0, nên lệnh QUYẾT TOÁN của nó ra
			   0đ và khoản tạm ứng vẫn treo trên TK 141 — im lặng.
			   ⚠️ CHỈ LÙI KHI Ô ẤY THẬT SỰ TRỐNG. Gửi thẳng số 0 là lời khai có chủ ý ("hàng được
			      tặng"), giữ nguyên 0; đè nó là tự sinh ra một khoản chi không có thật. */
			'thuc_te'    => ( ( null === $g( 'thucTe' ) || '' === $g( 'thucTe' ) ) && $sl * $dg > 0 )
				? ( $sl * $dg ) : VHCPVP_Util::num( $g( 'thucTe' ) ),
			'so_luong'   => $sl,
			'don_gia'    => $dg,
			'thanh_tien' => $sl * $dg,
			'vat'        => VHCPVP_Util::st( $g( 'vat' ) ),
			'anh'        => VHCPVP_Util::st( $g( 'anh' ) ),
			'gian'       => VHCPVP_Util::st( $g( 'gian' ) ),
			'note'       => VHCPVP_Util::st( $g( 'note' ) ),
			'cap_cha'    => $cap,
			'hinh_thuc'  => VHCPVP_Util::st( $g( 'hinhThuc' ) ),
			'ho_so'      => VHCPVP_Util::st( $g( 'hoSo' ) ),
		);
	}

	/**
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 CHI PHÍ DỰ ÁN KHÔNG ĐẺ THÊM MỤC CON NỮA — CHỐT Ở ĐÂY, KHÔNG PHẢI Ở NÚT BẤM.
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 17/09/2026: *"Đối với chi phí dự án, hạng mục con bỏ đi, vì các chi phí đều là
	 * hạng mục lớn"*, và chốt tiếp: giữ nguyên mục con ĐANG CÓ, chỉ cấm tạo mới.
	 *
	 * Gỡ nút `＋ con` và `↳ vào mục` trên màn là ĐỦ CHO NGƯỜI DÙNG, nhưng không đủ cho hệ thống:
	 * `saveDuAnLine` là một cửa API công khai, và mọi đường ghi khác (nạp tệp, gọi từ bộ khác,
	 * một bản app.html cũ còn nằm trong bộ nhớ đệm của trình duyệt ai đó) đều đi thẳng vào đây.
	 * Chốt ở nút bấm thì mục con vẫn mọc lại được, và mọc IM LẶNG — nó chỉ lộ ra ở chỗ tiền của
	 * hạng mục cha bỗng chuyển sang tính bằng "tổng con".
	 *
	 * ⚠️ `(Phát sinh)` KHÔNG PHẢI MỤC CON. Nó là khoản nảy ra lúc thi công, đứng độc lập, không
	 *    thuộc hạng mục nào — anh Thắng không bảo bỏ nó, và bỏ nhầm là mất một loại chi phí thật.
	 * ⚠️ DÒNG CŨ SỬA ĐƯỢC MÀ KHÔNG RƠI CẤP. Giữ nguyên cha của chính nó thì cho qua; chỉ chặn
	 *    khi ai đó gán một cha MỚI. Không có vế ấy thì mở một mục con cũ ra sửa mỗi cái ghi chú
	 *    cũng bị chối, và người ta sẽ đi xoá dòng — mất luôn ảnh bill lẫn hồ sơ đính kèm.
	 *
	 * @param string $cap_moi Cha mà lượt ghi này muốn đặt.
	 * @param string $cap_cu  Cha dòng ấy đang có ('' nếu là dòng mới).
	 * @return string Câu chối, '' nghĩa là cho qua.
	 */
	private static function loi_mo_muc_con_( $cap_moi, $cap_cu = '' ) {
		$moi = trim( (string) $cap_moi );
		$cu  = trim( (string) $cap_cu );
		if ( '' === $moi || '(Phát sinh)' === $moi ) { return ''; }
		if ( $moi === $cu ) { return ''; }   // dòng con cũ, giữ nguyên cha -> sửa thoải mái
		return 'Chi phí dự án nay chỉ có hạng mục lớn — không thêm mục con nữa. '
			. 'Nhập "' . $moi . '" thành một hạng mục lớn đứng riêng, hoặc chọn "Phát sinh" nếu '
			. 'đây là khoản nảy ra lúc thi công.';
	}

	/**
	 * DỰNG LẠI MỘT DÒNG MỤC CON CŨ — CỬA DUY NHẤT ĐI VÒNG QUA `loi_mo_muc_con_()`.
	 *
	 * 🔴 KHÔNG PHẢI ĐƯỜNG CHO NGƯỜI DÙNG, và không có mặt trong bảng cửa API
	 *    (`class-vhcp-api.php`) — cố ý. Nó tồn tại vì đúng một lẽ: mục con CŨ có thật trong sổ
	 *    đang chạy, nên phải có cách dựng lại đúng hình dạng ấy để còn kiểm được rằng chúng vẫn
	 *    hiện đủ và vẫn tính đúng sau khi đóng đường tạo mới.
	 *
	 * ⚠️ Không có cửa này thì bộ thử KHÔNG dựng nổi dữ liệu cũ, và "giữ nguyên mục con đang có"
	 *    thành một lời hứa không ai kiểm được — thứ tệ hơn cả không hứa.
	 * ⚠️ Ai định dùng nó cho một tính năng mới thì dừng lại: anh Thắng đã chốt chi phí dự án chỉ
	 *    còn hạng mục lớn. Đây là cửa DI TRÚ, không phải cửa nghiệp vụ.
	 */
	public static function them_dong_muc_con_cu( $ma_da, $rec ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$data           = self::line_data( $rec );
		$data['ma_da']  = (string) $ma_da;
		$data['row_no'] = self::next_row( $ma_da );
		$wpdb->insert( VHCPVP_DB::t( 'da_line' ), $data );
		self::push_nd( $f['loai'], $data['noi_dung'] );
		return VHCPVP_Util::ok();
	}

	public static function add_line( $ma_da, $rec ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		// Dự án chi trực tiếp: chỉ ĐÃ ĐÓNG mới khóa. Trước đây còn chặn cả trạng thái
		// "Chờ kế toán duyệt" của luồng duyệt đã bỏ, làm gian cũ không nhận được dữ liệu.
		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		if ( $st === 'Đã đóng' ) {
			return VHCPVP_Util::err( 'Dự án đã đóng — bấm "Mở lại" rồi nhập' );
		}
		$data           = self::line_data( $rec );
		$_mc = self::loi_mo_muc_con_( $data['cap_cha'] );
		if ( '' !== $_mc ) { return VHCPVP_Util::err( $_mc ); }
		$data['ma_da']  = (string) $ma_da;
		$data['row_no'] = self::next_row( $ma_da );
		$wpdb->insert( VHCPVP_DB::t( 'da_line' ), $data );
		self::push_nd( $f['loai'], $data['noi_dung'] );
		return VHCPVP_Util::ok();
	}

	/**
	 * ĐÍNH ẢNH / HỒ SƠ THẲNG VÀO MỘT DÒNG, KHÔNG PHẢI MỞ FORM SỬA.
	 *
	 * Anh Thắng: *"cho phép thêm ảnh và đính kèm cả hồ sơ trực tiếp tại trang"*.
	 *
	 * Bản trước muốn gắn một cái bill vào dòng thì phải bấm ✏️, cuộn lên đầu bảng, chọn tệp,
	 * rồi bấm Cập nhật — bốn thao tác cho một tấm ảnh, và trong lúc ấy form đang giữ CẢ DÒNG:
	 * lỡ bấm nhầm Thêm dòng là ra một dòng trùng.
	 *
	 * 🔴 ĐỔI ĐÚNG MỘT Ô, KHÔNG GHI ĐÈ CẢ DÒNG. Dùng `update_line` với một `rec` dựng lại từ màn
	 *    là mọi ô khác đi qua đường ghi lại — ô nào màn đọc thiếu thì bị xoá trắng, im lặng.
	 *
	 * ⚠️ HỒ SƠ THÌ CỘNG THÊM, ẢNH THÌ THAY. Một dòng có nhiều hồ sơ (hợp đồng, biên bản, báo
	 *    giá) nhưng chỉ một tấm bill; đính hồ sơ thứ hai mà đè mất cái thứ nhất là mất chứng từ.
	 */
	/**
	 * SỬA ĐÚNG MỘT Ô CỦA MỘT DÒNG — cho lối gõ thẳng trong bảng.
	 *
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 Anh Thắng 18/09/2026: *"thay vì bấm sửa, thì cho sửa thẳng trong từng dòng đơn được
	 *    không"*. Bấm ✏️ là cả dòng nhảy lên cái form ở đầu trang, sửa một con số xong phải cuộn
	 *    lại tìm chỗ cũ — với bảng mười mấy dòng thì mỗi lần sửa là một vòng đi về.
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 *
	 * ⚠️ KHÔNG ĐI QUA `update_line()`. Hàm ấy ghi lại CẢ DÒNG từ những gì màn gửi lên, nên gửi
	 *    thiếu ô nào là ô ấy bị dọn về rỗng — im lặng. Một ô thì ghi đúng một ô.
	 * ⚠️ CHỈ MỞ MẤY Ô SỐ + GHI CHÚ. Hình thức chi, VAT, loại chi phí, "Thuộc" kéo theo cả dây
	 *    (mã tài khoản, đường tạm ứng, quan hệ cha–con) — mấy thứ ấy để form lo, vì ở đó còn có
	 *    dòng gợi ý và chốt đi kèm.
	 * ⚠️ ĐI QUA ĐỦ HAI CHỐT như `update_line`: hạng mục đã chốt sổ thì không đụng, và dự toán đã
	 *    lên lệnh thì đóng (`loi_sua_du_toan_`). Mở một cửa mới mà quên chốt cũ là chốt ấy coi
	 *    như không có — người ta chỉ cần đổi lối vào.
	 * ⚠️ SỐ LƯỢNG / ĐƠN GIÁ ĐỔI THÌ TÍNH LẠI THÀNH TIỀN ngay trong cùng một lượt ghi. Để màn tự
	 *    tính rồi gửi thêm một lượt nữa là có lúc lệch — và lệch ở cột tiền.
	 */
	public static function dat_o_line( $ma_da, $row, $cot, $gt ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		if ( 'Đã đóng' === $st ) { return VHCPVP_Util::err( 'Dự án đã đóng — bấm "Mở lại" rồi sửa' ); }
		$row = (int) $row;
		$cot = trim( (string) $cot );
		$map = array(
			'duToan'  => 'du_toan',
			'soLuong' => 'so_luong',
			'donGia'  => 'don_gia',
			'thucTe'  => 'thuc_te',
			'note'    => 'note',
		);
		if ( ! isset( $map[ $cot ] ) ) {
			return VHCPVP_Util::err( 'Ô "' . $cot . '" không sửa thẳng trong bảng được — bấm ✏️ để mở form.' );
		}
		$_c = self::loi_hang_muc_da_chot_( $ma_da, $row, 'sửa dòng' );
		if ( '' !== $_c ) { return VHCPVP_Util::err( $_c ); }
		$t   = VHCPVP_DB::t( 'da_line' );
		$cur = VHCPVP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s AND row_no=%d", (string) $ma_da, $row ) );
		if ( ! $cur ) { return VHCPVP_Util::err( 'Dòng không hợp lệ' ); }

		$sua = array();
		if ( 'note' === $cot ) {
			$sua['note'] = VHCPVP_Util::st( $gt );
		} else {
			$so = VHCPVP_Util::num( $gt );
			/* Mục con không có dự toán riêng — tiền của nó nằm ở cột thực tế / thành tiền. */
			if ( 'duToan' === $cot && '' !== trim( (string) $cur['cap_cha'] ) ) {
				return VHCPVP_Util::err( 'Mục con không có ô dự toán riêng — dự toán nằm ở hạng mục lớn.' );
			}
			$sua[ $map[ $cot ] ] = $so;
			if ( 'soLuong' === $cot || 'donGia' === $cot ) {
				$sl = ( 'soLuong' === $cot ) ? $so : VHCPVP_Util::num( $cur['so_luong'] );
				$dg = ( 'donGia' === $cot )  ? $so : VHCPVP_Util::num( $cur['don_gia'] );
				$sua['thanh_tien'] = $sl * $dg;
			}
		}
		$_dt = self::loi_sua_du_toan_( $ma_da, $row, $cur, $sua );
		if ( '' !== $_dt ) { return VHCPVP_Util::err( $_dt ); }

		$wpdb->update( $t, $sua, array( 'ma_da' => (string) $ma_da, 'row_no' => $row ) );
		VHCPVP_Log::log_action( array(
			'actor'  => VHCPVP_Auth::nguoi(),
			'role'   => VHCPVP_Auth::vai_tro(),
			'action' => 'Sửa ô trong bảng dự án',
			'target' => (string) $ma_da . '#' . $row,
			'detail' => $cot . ': ' . VHCPVP_Util::st( $gt ),
		) );
		return VHCPVP_Util::ok();
	}

	public static function dat_anh_line( $ma_da, $row, $url ) {
		return self::sua_o_line_( $ma_da, $row, 'anh', trim( (string) $url ), false, 'Đính ảnh vào dòng dự án' );
	}

	public static function them_ho_so_line( $ma_da, $row, $url ) {
		return self::sua_o_line_( $ma_da, $row, 'ho_so', trim( (string) $url ), true, 'Đính hồ sơ vào dòng dự án' );
	}

	/** Gỡ ảnh của một dòng (đính nhầm thì phải gỡ được, không thì dòng mang chứng từ của việc khác). */
	public static function go_anh_line( $ma_da, $row ) {
		return self::sua_o_line_( $ma_da, $row, 'anh', '', false, 'Gỡ ảnh khỏi dòng dự án' );
	}

	private static function sua_o_line_( $ma_da, $row, $cot, $url, $cong_them, $viec ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		if ( 'Đã đóng' === $st ) { return VHCPVP_Util::err( 'Dự án đã đóng — bấm "Mở lại" rồi sửa' ); }
		$row = (int) $row;
		$t   = VHCPVP_DB::t( 'da_line' );
		$cu  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s AND row_no=%d", (string) $ma_da, $row ), ARRAY_A );
		if ( ! $cu ) { return VHCPVP_Util::err( 'Không tìm thấy dòng ' . $row ); }

		$_c = self::loi_hang_muc_da_chot_( $ma_da, $row, 'đổi chứng từ' );
		if ( '' !== $_c ) { return VHCPVP_Util::err( $_c ); }

		$moi = $url;
		if ( $cong_them && '' !== $url ) {
			$co = trim( (string) $cu[ $cot ] );
			$ds = preg_split( '/\s+/u', $co, -1, PREG_SPLIT_NO_EMPTY );
			if ( ! in_array( $url, $ds, true ) ) { $ds[] = $url; }
			$moi = implode( "\n", $ds );
		}
		$wpdb->update( $t, array( $cot => $moi ), array( 'ma_da' => (string) $ma_da, 'row_no' => $row ) );
		VHCPVP_Log::log_action( array(
			'actor'  => VHCPVP_Auth::nguoi(),
			'role'   => VHCPVP_Auth::vai_tro(),
			'action' => (string) $viec,
			'target' => (string) $ma_da . '#' . $row,
			'detail' => trim( (string) $cu['noi_dung'] ) . ( '' !== $url ? ( ' — ' . $url ) : ' — gỡ' ),
		) );
		$ra = array( 'row' => $row );
		$ra[ 'anh' === $cot ? 'anh' : 'hoSo' ] = $moi;
		return VHCPVP_Util::ok( $ra );
	}

	public static function update_line( $ma_da, $row, $rec ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		if ( $st === 'Đã đóng' ) { return VHCPVP_Util::err( 'Dự án đã đóng — bấm "Mở lại" rồi sửa' ); }
		$row = (int) $row;
		/* ⚠️ SỬA CHẶN NHƯ XOÁ. Anh Thắng nói *"không cho xoá dòng"*, nhưng để hở nút sửa thì gõ
		   tiền về 0 là xoá trá hình, chỉ khác cái tên. */
		$_c = self::loi_hang_muc_da_chot_( $ma_da, $row, 'sửa dòng' );
		if ( '' !== $_c ) { return VHCPVP_Util::err( $_c ); }
		if ( $row < self::DATA_ROW ) { return VHCPVP_Util::err( 'Dòng không hợp lệ' ); }
		$t   = VHCPVP_DB::t( 'da_line' );
		$cur = VHCPVP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s AND row_no=%d", (string) $ma_da, $row ) );
		if ( ! $cur ) { return VHCPVP_Util::err( 'Dòng không hợp lệ' ); }
		$old_name = trim( (string) $cur['noi_dung'] );
		$data     = self::line_data( $rec );
		$_mc = self::loi_mo_muc_con_( $data['cap_cha'], (string) $cur['cap_cha'] );
		if ( '' !== $_mc ) { return VHCPVP_Util::err( $_mc ); }
		$_dt = self::loi_sua_du_toan_( $ma_da, $row, $cur, $data );
		if ( '' !== $_dt ) { return VHCPVP_Util::err( $_dt ); }
		$wpdb->update( $t, $data, array( 'ma_da' => (string) $ma_da, 'row_no' => $row ) );
		if ( $data['cap_cha'] === '' && $old_name !== '' && $old_name !== $data['noi_dung'] ) {
			self::relink_children( $ma_da, $old_name, $data['noi_dung'] );   // hạng mục lớn đổi tên -> cập nhật mục con
		}
		self::push_nd( $f['loai'], $data['noi_dung'] );
		return VHCPVP_Util::ok();
	}

	/**
	 * Dòng này có thuộc một hạng mục ĐÃ CHỐT không — trả câu lỗi, '' nghĩa là đụng được.
	 *
	 * 🔴 Anh Thắng: *"Chốt xong bill quyết toán thì không cho xoá dòng"*.
	 *
	 *    "Đã chốt" nghĩa là hạng mục đã có hoá đơn và đã khoá là CHI THỰC TẾ; con số ấy có thể
	 *    đã nằm trong một lệnh quyết toán kế toán đã chốt sổ. Xoá một dòng con của nó là tổng
	 *    tiền tụt xuống sau lưng kế toán, mà lệnh quyết toán vẫn ghi con số cũ — hai chỗ nói hai
	 *    số cho cùng một khoản, và không ai biết bên nào đúng.
	 *
	 * ⚠️ ÁP CHO CẢ MỤC CON. Khoá mỗi hàng cha là hở hẳn đường sau: mấy dòng con mới là chỗ
	 *    chứa tiền, xoá chúng thì cha vẫn "đã chốt" mà tổng đã khác.
	 *
	 * ⚠️ SỬA CŨNG CHẶN NHƯ XOÁ. Để hở nút sửa thì gõ tiền về 0 là xoá trá hình, chỉ khác cái tên.
	 */
	/**
	 * NHẬT KÝ CỦA MỘT DỰ ÁN — ai chỉnh gì, lúc nào.
	 *
	 * Anh Thắng: *"Đầu trang bổ sung tiến trình như này và lịch sử đơn để theo dõi đơn và chỉnh
	 * sửa"* — đúng khối đã có ở trang đơn tuần, nay dựng cho trang dự án.
	 *
	 * 🔴 VẾT CỦA MỘT DỰ ÁN NẰM Ở BA KIỂU KHOÁ, không phải một:
	 *      `DA_xxx`            — việc của cả dự án (đặt dự toán, đặt kỳ…)
	 *      `DA_xxx#12`         — việc của một dòng (đính ảnh, đổi trạng thái hạng mục)
	 *      `DA_xxx · đợt 2`    — việc của một lệnh tạm ứng / quyết toán
	 *    và màn còn ghi thêm vết theo TÊN dự án (`_log(...)` gửi tên, không gửi mã).
	 *    Tra thiếu kiểu nào là nhật ký khuyết đúng loại việc ấy — mà người ta mở nhật ký ra
	 *    chính là để tìm cái mình không nhớ.
	 *
	 * ⚠️ KHÔNG DÙNG `LIKE 'DA_xxx%'` TRƠN: mã này có thể là tiền tố của mã khác (`DA_ab` nằm
	 *    trong `DA_abc`), và thế là nhật ký dự án này lẫn việc của dự án kia.
	 */
	public static function nhat_ky_du_an( $ma_da, $limit = 50 ) {
		global $wpdb;
		$ma_da = trim( (string) $ma_da );
		if ( '' === $ma_da ) { return VHCPVP_Util::ok( array( 'items' => array() ) ); }
		$limit = (int) $limit;
		if ( $limit <= 0 || $limit > 300 ) { $limit = 50; }
		$f   = self::find( $ma_da );
		$ten = $f ? trim( (string) $f['ten'] ) : '';
		$t   = VHCPVP_DB::t( 'log' );

		/* 🔴 SO TIỀN TỐ BẰNG `SUBSTR`, KHÔNG BẰNG `LIKE`. Mã dự án có dấu gạch dưới (`DA_xxx`),
		   mà `_` trong LIKE là ký tự đại diện — không thoát thì nhật ký dự án này lẫn việc của
		   dự án kia. Thoát bằng `esc_like` thì lại rơi vào chỗ MySQL và SQLite cư xử KHÁC NHAU:
		   MySQL mặc định coi `\` là ký tự thoát trong LIKE, SQLite thì không (phải khai
		   `ESCAPE`). Mã chạy đúng trên máy thật mà sai trên bài kiểm là kiểu hỏng tệ nhất — nó
		   dạy người ta rằng bài kiểm sai. `SUBSTR` thì hai bên hiểu như nhau. */
		$dk  = array( 'doi_tuong = %s' ); $th = array( $ma_da );
		$k1  = $ma_da . '#';
		$dk[] = 'SUBSTR(doi_tuong,1,%d) = %s'; $th[] = mb_strlen( $k1 ); $th[] = $k1;
		$k2  = $ma_da . ' · ';
		$dk[] = 'SUBSTR(doi_tuong,1,%d) = %s'; $th[] = mb_strlen( $k2 ); $th[] = $k2;
		if ( '' !== $ten ) { $dk[] = 'doi_tuong = %s'; $th[] = $ten; }
		$th[] = $limit;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $t WHERE (" . implode( ' OR ', $dk ) . ") ORDER BY id DESC LIMIT %d",
			$th ), ARRAY_A );

		$items = array();
		foreach ( (array) $rows as $r ) {
			$tg = VHCPVP_Util::fmt_dt( $r['tg'] );
			if ( '' !== $tg && VHCPVP_Util::ngay_vo_ly( substr( $tg, 0, 10 ) ) ) { $tg = ''; }
			$items[] = array(
				'tg'       => $tg,
				'nguoi'    => (string) $r['nguoi'],
				'vaiTro'   => (string) $r['vai_tro'],
				'hanhDong' => (string) $r['hanh_dong'],
				'chiTiet'  => (string) $r['chi_tiet'],
			);
		}
		return VHCPVP_Util::ok( array( 'items' => $items ) );
	}

	/**
	 * Dòng này thuộc HẠNG MỤC LỚN nào — trả số dòng của hạng mục ấy, 0 nếu không tra được.
	 *
	 * Dòng cha thì là chính nó; mục con thì tra ngược theo tên ở cột "Thuộc". Dòng `(Phát sinh)`
	 * không thuộc hạng mục nào nên trả 0.
	 */
	private static function hm_lon_cua_dong_( $ma_da, $row, $cur ) {
		$cap = trim( (string) $cur['cap_cha'] );
		if ( '' === $cap ) { return (int) $row; }
		if ( '(Phát sinh)' === $cap ) { return 0; }
		foreach ( self::lines_of( $ma_da ) as $l ) {
			if ( trim( (string) $l['cap_cha'] ) === '' && trim( (string) $l['noi_dung'] ) === $cap ) {
				return (int) $l['row_no'];
			}
		}
		return 0;
	}

	/**
	 * Sửa SỐ DỰ TOÁN của một hạng mục đã lên lệnh — trả câu lỗi, '' nghĩa là sửa được.
	 *
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 ĐÃ XIN VÀ ĐÃ LÊN LỆNH THÌ SỐ DỰ TOÁN ĐÓNG LẠI.
	 * ═════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 18/09/2026: *"Số dự toán đã xin và lên thì không được sửa"*, kèm ảnh một đơn có
	 * thẻ đỏ *"⚠️ lệnh đã gửi vượt dự kiến 14.970.000đ"* — vì mấy dòng dự toán bị gõ về 0 SAU
	 * khi lệnh 68.790.000đ đã gửi và đã cấp tiền.
	 *
	 * Số tiền của LỆNH chốt cứng lúc gửi (đúng thế), nhưng "dự kiến tạm ứng" thì cộng lại từ
	 * mấy dòng này mỗi lần mở trang. Sửa dòng sau khi gửi là hai con số tách nhau ra, và màn
	 * hình bắt đầu tố cáo một chuyện không có thật: lệnh trông như xin vượt dự toán, trong khi
	 * lúc gửi nó khớp. Người đọc không có cách nào biết bên nào mới đúng.
	 *
	 * ⚠️ CHỈ KHOÁ CỘT DỰ TOÁN (dự toán · số lượng · đơn giá · thành tiền). CỘT THỰC TẾ VẪN MỞ —
	 *    đó là cả quy trình: cầm tiền đi tiêu rồi mới về ghi số thật. Khoá luôn cột ấy là không
	 *    ai quyết toán được nữa.
	 * ⚠️ MỞ LẠI BẰNG CÁCH TRẢ LỆNH, không phải bằng một cái nút riêng. Trả lệnh đưa hạng mục về
	 *    'tra' và ở đó sửa thoải mái — đường ấy có người duyệt, còn một nút "mở khoá dự toán"
	 *    thì không.
	 * ⚠️ ÁP CHO CẢ MỤC CON: tiền của hạng mục cha cộng từ con, nên sửa con là đổi số của cha.
	 */
	private static function loi_sua_du_toan_( $ma_da, $row, $cur, $data ) {
		$lon = self::hm_lon_cua_dong_( $ma_da, $row, $cur );
		if ( ! $lon ) { return ''; }
		$h = self::hm_cua( $ma_da, $lon );
		if ( in_array( $h['tt'], array( 'nhap', 'tra' ), true ) ) { return ''; }
		$doi = array();
		foreach ( array( 'du_toan' => 'dự toán', 'so_luong' => 'số lượng',
			'don_gia' => 'đơn giá', 'thanh_tien' => 'thành tiền' ) as $cot => $ten ) {
			if ( ! array_key_exists( $cot, $data ) ) { continue; }
			/* ⚠️ ÉP KIỂU `(float)` CẢ HAI BÊN — ĐÂY MỚI LÀ CHỖ GÁNH. Sổ trả về chuỗi "0.00", còn
			   `line_data()` trả số nguyên 0; trong PHP `0 !== 0.0` là ĐÚNG, nên so chặt hai thứ
			   ấy là mọi lần sửa đều bị chặn oan, kể cả lúc chẳng đụng tới đồng nào. Đã cắn thật
			   lúc dựng chốt này: sáu phép đỏ, mà không phép nào nói ra nguyên nhân.
			   Khe hở 0,5đ là lưới đỡ thêm cho sai số dấu phẩy động của cột DECIMAL — tiền ở đây
			   là số nguyên đồng nên không có thay đổi thật nào nhỏ hơn thế. */
			$moi = (float) VHCPVP_Util::num( $data[ $cot ] );
			$cu  = (float) VHCPVP_Util::num( isset( $cur[ $cot ] ) ? $cur[ $cot ] : 0 );
			if ( abs( $moi - $cu ) >= 0.5 ) { $doi[] = $ten; }
		}
		if ( ! $doi ) { return ''; }
		$ten_lon = '';
		foreach ( self::lines_of( $ma_da ) as $l ) {
			if ( (int) $l['row_no'] === $lon ) { $ten_lon = trim( (string) $l['noi_dung'] ); }
		}
		return 'Hạng mục "' . $ten_lon . '" đã lên lệnh tạm ứng rồi — không sửa '
			. implode( ' / ', $doi ) . ' được nữa. '
			. 'Số tiền của lệnh đã chốt lúc gửi; sửa ở đây là hai con số tách nhau ra. '
			. 'Cần sửa thì trả lệnh về trước, rồi gửi lại. '
			. '(Cột "Chi phí thực tế" vẫn ghi được bình thường.)';
	}

	public static function loi_hang_muc_da_chot_( $ma_da, $row, $viec = 'sửa' ) {
		global $wpdb;
		$t   = VHCPVP_DB::t( 'da_line' );
		$cur = VHCPVP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s AND row_no=%d", (string) $ma_da, (int) $row ) );
		if ( ! $cur ) { return ''; }   // dòng không có thật thì để lối gọi tự báo
		$khoa_row = self::hm_lon_cua_dong_( $ma_da, (int) $row, $cur );
		if ( ! $khoa_row || ! self::hm_khoa( $ma_da, $khoa_row ) ) { return ''; }
		$h = self::hm_cua( $ma_da, $khoa_row );
		return 'Hạng mục đã chốt là chi thực tế — không ' . $viec . ' được nữa'
			. ( $h['qtDot'] > 0 ? ( ' (đã gửi quyết toán lệnh ' . $h['qtDot'] . ')' ) : '' )
			. '. Kế toán bấm "🔓 Mở lại" thì mới đụng được.';
	}

	public static function delete_line( $ma_da, $row ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		if ( $st === 'Đã đóng' ) { return VHCPVP_Util::err( 'Dự án đã đóng — bấm "Mở lại" rồi xóa' ); }
		$row = (int) $row;
		if ( $row < self::DATA_ROW ) { return VHCPVP_Util::err( 'Dòng không hợp lệ' ); }
		$t   = VHCPVP_DB::t( 'da_line' );
		$cur = VHCPVP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s AND row_no=%d", (string) $ma_da, $row ) );
		if ( ! $cur ) { return VHCPVP_Util::err( 'Dòng không hợp lệ' ); }
		$_c = self::loi_hang_muc_da_chot_( $ma_da, $row, 'xoá dòng' );
		if ( '' !== $_c ) { return VHCPVP_Util::err( $_c ); }
		$nm  = trim( (string) $cur['noi_dung'] );
		$cap = trim( (string) $cur['cap_cha'] );
		if ( $cap === '' && $nm !== '' ) { self::relink_children( $ma_da, $nm, '(Phát sinh)' ); }   // xóa hạng mục lớn -> mục con thành phát sinh
		$wpdb->delete( $t, array( 'ma_da' => (string) $ma_da, 'row_no' => $row ) );
		return VHCPVP_Util::ok();
	}

	private static function relink_children( $ma_da, $old, $new ) {
		global $wpdb;
		$t = VHCPVP_DB::t( 'da_line' );
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET cap_cha=%s WHERE ma_da=%s AND TRIM(cap_cha)=%s", (string) $new, (string) $ma_da, (string) $old ) );
	}

	// ---------------------------------------------------------------- quy trình

	private static function set_status( $ma_da, $status ) {
		global $wpdb;
		$wpdb->update( VHCPVP_DB::t( 'da_index' ), array( 'trang_thai' => (string) $status ), array( 'ma_da' => (string) $ma_da ) );
	}

	public static function submit( $ma_da ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		if ( self::la_coso_chung( $ma_da ) ) { return VHCPVP_Util::err( 'Sổ Chi phí cơ sở CHUNG không cần duyệt' ); }
		if ( (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' ) !== 'Đang làm' ) { return VHCPVP_Util::err( 'Chỉ gửi khi đang làm' ); }
		self::set_status( $ma_da, 'Chờ kế toán duyệt' );
		return VHCPVP_Util::ok();
	}

	public static function approve( $ma_da, $nguoi ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		if ( (string) $f['trang_thai'] !== 'Chờ kế toán duyệt' ) { return VHCPVP_Util::err( 'Dự án không ở trạng thái chờ duyệt' ); }
		self::set_status( $ma_da, 'Đã duyệt' );
		VHCPVP_Meta::set( 'daApp_' . $ma_da, VHCPVP_Util::now()->format( 'd/m/Y' ) );   // ngày chứng từ khi xuất MISA
		return VHCPVP_Util::ok();
	}

	public static function ret( $ma_da ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		if ( (string) $f['trang_thai'] !== 'Chờ kế toán duyệt' ) { return VHCPVP_Util::err( 'Chỉ trả khi đang chờ duyệt' ); }
		self::set_status( $ma_da, 'Đang làm' );
		return VHCPVP_Util::ok();
	}

	/**
	 * Đóng dự án. Dự án CHI TRỰC TIẾP — không có bước xin/duyệt tạm ứng như đơn tuần,
	 * nên đang làm là đóng được luôn, không đòi phải "Đã duyệt" trước.
	 */
	public static function close( $ma_da ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		if ( self::la_coso_chung( $ma_da ) ) { return VHCPVP_Util::err( 'Sổ Chi phí cơ sở CHUNG không đóng — nó chạy xuyên suốt' ); }
		if ( (string) $f['trang_thai'] === 'Đã đóng' ) { return VHCPVP_Util::err( 'Dự án đã đóng' ); }
		self::set_status( $ma_da, 'Đã đóng' );
		return VHCPVP_Util::ok();
	}

	public static function reopen( $ma_da ) {
		if ( ! self::find( $ma_da ) ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		self::set_status( $ma_da, 'Đang làm' );
		return VHCPVP_Util::ok();
	}

	public static function delete( $ma_da ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCPVP_Util::err( 'Không tìm thấy dự án' ); }
		/* SỔ CHI PHÍ CƠ SỞ CHUNG: chỉ xoá được KHI RỖNG — anh Thắng 11/09/2026: *"xoá đơn này
		   cho anh"*, chỉ vào dòng sổ chung đang 0đ / 0 dòng.

		   🔴 KHÔNG BỎ HẲN CHỐT. Sổ chung của những nơi đã chạy lâu mang dòng chi ĐÃ XUẤT MISA;
		      xoá là mất chứng từ kế toán, không khôi phục được. Rỗng thì không có gì để mất,
		      mà để lại một dòng 0đ nằm giữa danh sách thì lần nào lọc cũng phải lướt qua nó.

		   ⚠️ ĐẾM CẢ DÒNG Ở SỔ CHI PHÍ. Dự án có thể không còn dòng nào trong bảng của nó mà
		      vẫn đang gánh các dòng chi mang mã ấy bên sổ chi phí — nhìn bảng hạng mục thì
		      tưởng rỗng. */
		$la_chung = self::la_coso_chung( $ma_da );
		if ( $la_chung ) {
			$n_dong = 0;
			foreach ( self::lines_of( $ma_da ) as $r ) { if ( self::is_real( $r ) ) { $n_dong++; } }
			$n_sc = count( VHCPVP_SoChi::theo_du_an( (string) $ma_da ) );
			if ( ! $n_sc ) { $n_sc = count( VHCPVP_SoChi::theo_du_an( (string) $f['ten'] ) ); }
			if ( $n_dong || $n_sc ) {
				return VHCPVP_Util::err( 'Sổ Chi phí cơ sở CHUNG còn ' . ( $n_dong + $n_sc )
					. ' dòng chi — không xoá được. Dòng ở đây có thể đã xuất MISA; xoá hết dòng trước rồi mới xoá sổ.' );
			}
		}
		if ( (string) $f['trang_thai'] === 'Đã đóng' ) { return VHCPVP_Util::err( 'Dự án đã đóng — Admin "Mở lại" trước khi xóa' ); }
		$wpdb->delete( VHCPVP_DB::t( 'da_line' ), array( 'ma_da' => (string) $ma_da ) );
		$wpdb->delete( VHCPVP_DB::t( 'da_index' ), array( 'ma_da' => (string) $ma_da ) );
		VHCPVP_Meta::del( 'daPay_' . $ma_da );
		VHCPVP_Meta::del( 'daApp_' . $ma_da );
		VHCPVP_Meta::del( 'daKy_' . $ma_da );
		/* XOÁ SỔ CHUNG THÌ GHIM LẠI VẾT TRỐNG.
		   ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG, ghi lại để lần sau khỏi đuổi theo: bỏ dòng này KHÔNG đổi kết
		      quả hôm nay — vết ghim cũ vẫn trỏ tới mã vừa xoá, mà `ma_coso_chung()` thấy
		      `find()` trả null nên đã trả '' rồi. Giữ vì nó CHỐT trạng thái "sổ này không có sổ
		      chung" thay vì để mỗi lượt gọi đi tra một mã đã chết; và ngày nào vết ghim bị dọn
		      (đổi khoá meta, khôi phục từ bản lưu) thì `ma_coso_chung()` ngã về "dự án loại
		      'Chi phí cơ sở' CŨ NHẤT" — tức một ĐƠN THEO TUẦN của nhân viên — và đơn ấy lặng lẽ
		      thành sổ chung: không đóng, không xoá, không đổi tên được, không báo gì. */
		if ( $la_chung ) { VHCPVP_Meta::set( self::MK_COSO_CHUNG, '-' ); }
		return VHCPVP_Util::ok();
	}

	/** Loại dự án -> loại chi phí tương ứng trong danh mục (tên trùng khớp, không phải đoán). */
	public static function loai_cp_mac_dinh( $loai_du_an ) {
		/* ⚠️ BA LOẠI CỦA MARKETING CHƯA CÓ DÒNG RIÊNG TRONG DANH MỤC LOẠI CHI PHÍ, nên để trống
		   ở đây là ĐÚNG: trống thì ô Loại chi phí trên màn không bị chọn sẵn một dòng sai, và
		   người nhập tự chọn loại của họ ("Chi phí marketing", "Chi Phí MKT - Hoạt náo"…).
		   Ngày nào anh Thắng khai dòng riêng cho chúng thì thêm vào bảng này một dòng là xong. */
		$map = array(
			'Tháo dỡ'       => 'Chi phí tháo dỡ',
			'Setup lắp đặt' => 'Chi phí setup lắp đặt gian hàng mới',
			'Chi phí cơ sở' => 'Chi phí cơ sở',
		);
		$k = trim( (string) $loai_du_an );
		return isset( $map[ $k ] ) ? $map[ $k ] : '';
	}

	/**
	 * Gán loại chi phí + mã tài khoản cho dòng hạng mục CŨ.
	 * Dòng chưa có loại -> lấy theo loại dự án (Tháo dỡ / Setup lắp đặt / Chi phí cơ sở).
	 */
	public static function gan_ma_tai_khoan( $all = false ) {
		global $wpdb;
		$t = VHCPVP_DB::t( 'da_line' );
		$n = 0; $thieu = array(); $khong_suy = 0;
		foreach ( self::all_with_lines() as $p ) {
			$mac_dinh  = self::loai_cp_mac_dinh( $p['loai'] );
			$parent_ht = array();
			foreach ( $p['lines'] as $x ) {
				if ( trim( (string) $x['cap_cha'] ) === '' ) { $parent_ht[ trim( (string) $x['noi_dung'] ) ] = trim( (string) $x['hinh_thuc'] ); }
			}
			foreach ( $p['lines'] as $x ) {
				$cu   = trim( (string) $x['loai_cp'] );
				$loai = ( $cu !== '' ) ? $cu : $mac_dinh;
				if ( $loai === '' ) { $khong_suy++; continue; }
				if ( ! $all && $cu !== '' && trim( (string) $x['tk_no'] ) !== '' ) { continue; }
				$cap = trim( (string) $x['cap_cha'] );
				$ht  = ( $cap !== '' && $cap !== '(Phát sinh)' && ! empty( $parent_ht[ $cap ] ) ) ? $parent_ht[ $cap ] : trim( (string) $x['hinh_thuc'] );
				$gian_x = trim( (string) $x['gian'] );
				$giu    = VHCPVP_Cfg::ma_con_hop_le( $loai, $gian_x, $x['tk_no'] );
				$tk  = VHCPVP_Cfg::resolve_tk( $loai, $ht, array( 'tkNo' => $giu ), $gian_x );
				if ( $tk['tk_no'] === '' ) { $thieu[ $loai ] = 1; }
				if ( $loai === $cu && $tk['tk_no'] === trim( (string) $x['tk_no'] ) && $tk['tk_co'] === trim( (string) $x['tk_co'] ) ) { continue; }
				$wpdb->update( $t, array( 'loai_cp' => $loai, 'tk_no' => $tk['tk_no'], 'tk_co' => $tk['tk_co'], 'ma_dt' => $tk['ma_dt'] ), array( 'id' => (int) $x['id'] ) );
				$n++;
			}
		}
		return VHCPVP_Util::ok( array( 'updated' => $n, 'thieuMa' => array_keys( $thieu ), 'khongSuyDuoc' => $khong_suy ) );
	}

	/**
	 * Dùng chung cho báo cáo: mọi dự án kèm dòng hạng mục — ĐÚNG 2 LỆNH DB
	 * (1 lệnh danh mục + 1 lệnh toàn bộ dòng, rồi gom trong PHP).
	 * Trước đây mỗi dự án 1 lệnh nên 30 dự án là 31 lệnh.
	 */
	public static function all_with_lines() {
		$ti = VHCPVP_DB::t( 'da_index' );
		$tl = VHCPVP_DB::t( 'da_line' );
		$rows = VHCPVP_DB::rows( "SELECT * FROM $ti ORDER BY stt ASC" );
		$by   = array();
		foreach ( VHCPVP_DB::rows( "SELECT * FROM $tl ORDER BY ma_da ASC, row_no ASC" ) as $l ) {
			$by[ (string) $l['ma_da'] ][] = $l;
		}
		foreach ( $rows as $i => $r ) {
			$k = (string) $r['ma_da'];
			$rows[ $i ]['lines'] = isset( $by[ $k ] ) ? $by[ $k ] : array();
		}
		return $rows;
	}

	/** Toàn bộ ghi nhận chi tiền của mọi dự án — 1 lệnh DB. */
	public static function pay_map() {
		$out = array();
		foreach ( VHCPVP_Meta::get_prefix( 'daPay_' ) as $k => $v ) {
			$o = json_decode( (string) $v, true );
			$out[ substr( $k, 7 ) ] = is_array( $o ) ? $o : array();
		}
		return $out;
	}

	/** Ngày kế toán duyệt của mọi dự án — 1 lệnh DB. */
	public static function approve_date_map() {
		$out = array();
		foreach ( VHCPVP_Meta::get_prefix( 'daApp_' ) as $k => $v ) {
			$out[ substr( $k, 7 ) ] = (string) $v;
		}
		return $out;
	}
}
