<?php
/**
 * CHI PHÍ KỸ THUẬT — dự án Tháo dỡ / Setup lắp đặt + sheet "Chi phí cơ sở" chung xuyên suốt.
 *
 * App cũ mỗi dự án là 1 tab Google Sheet; ở đây là các dòng trong vhcp_da_line khóa theo
 * (ma_da, row_no). row_no vẫn bắt đầu từ 5 để giao diện gọi updateDuAnLine(maDA, row, rec) như cũ.
 *
 * Quy ước tính tiền giữ nguyên:
 *   - Hạng mục lớn (cap_cha rỗng) mang DỰ TOÁN. Thực tế của nó chỉ tính khi KHÔNG có mục con.
 *   - Mục con / (Phát sinh) chỉ mang THỰC TẾ; hình thức chi thừa hưởng của hạng mục cha.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_DuAn {

	const DATA_ROW = 5;

	public static function find( $ma_da ) {
		global $wpdb;
		$t = VHCP_DB::t( 'da_index' );
		return VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s", (string) $ma_da ) );
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
		return VHCP_DonVi::cua_nguoi( isset( $r['nguoi_tao'] ) ? $r['nguoi_tao'] : '' );
	}

	private static function lines_of( $ma_da ) {
		global $wpdb;
		$t = VHCP_DB::t( 'da_line' );
		return VHCP_DB::rows( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s ORDER BY row_no ASC", (string) $ma_da ) );
	}

	private static function next_row( $ma_da ) {
		global $wpdb;
		$t   = VHCP_DB::t( 'da_line' );
		$max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(row_no) FROM $t WHERE ma_da=%s", (string) $ma_da ) );
		return max( self::DATA_ROW, $max + 1 );
	}

	/** Dòng "có nội dung": bỏ hàng trống hoàn toàn (giống điều kiện lọc của app cũ). */
	private static function is_real( $r ) {
		return ! ( trim( (string) $r['noi_dung'] ) === '' && ! ( VHCP_Util::num( $r['du_toan'] ) || VHCP_Util::num( $r['thuc_te'] ) ) );
	}

	// ---------------------------------------------------------------- tạo / danh sách

	public static function create_du_an( $loai, $ten, $nguoi, $tu = '', $den = '' ) {
		global $wpdb;
		$loai = trim( (string) $loai );
		if ( $loai === 'Chi phí cơ sở' ) {
			/* Tên rỗng = mở lại sổ CHUNG cũ (nút "🔧 Mở sổ chung (cũ)" vẫn gọi đúng như trước).
			   Có tên = lập MỘT ĐƠN chi phí cơ sở cho một TUẦN — xem chốt ở khối "MỘT ĐỢT LÀ
			   MỘT ĐƠN" bên dưới. */
			$ten_cs = VHCP_Util::san( $ten );
			return ( '' === $ten_cs ) ? self::ensure_co_so_chung( $nguoi ) : self::tao_don_coso( $ten_cs, $nguoi, $tu, $den );
		}
		$ten = VHCP_Util::san( $ten );
		if ( ! in_array( $loai, array( 'Tháo dỡ', 'Setup lắp đặt' ), true ) ) { return VHCP_Util::err( 'Loại không hợp lệ' ); }
		if ( $ten === '' ) { return VHCP_Util::err( 'Nhập tên dự án' ); }
		$ma = VHCP_Util::uid( 'DA' );
		$wpdb->insert( VHCP_DB::t( 'da_index' ), array(
			'ma_da'      => $ma,
			'ten'        => $ten,
			'loai'       => $loai,
			'trang_thai' => 'Đang làm',
			'ngay_tao'   => VHCP_Util::now_sql(),
			'nguoi_tao'  => (string) $nguoi,
		) );
		return VHCP_Util::ok( array( 'maDA' => $ma, 'ten' => $ten, 'loai' => $loai, 'sheet' => '', 'trangThai' => 'Đang làm', 'url' => '' ) );
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

	/** Mã đơn chi phí cơ sở CHUNG (sổ xuyên suốt cũ) — '' nghĩa là sổ này không có. */
	public static function ma_coso_chung() {
		global $wpdb;
		$m = trim( (string) VHCP_Meta::get( self::MK_COSO_CHUNG, '' ) );
		if ( '-' === $m ) { return ''; }
		if ( '' !== $m ) { return self::find( $m ) ? $m : ''; }
		$t = VHCP_DB::t( 'da_index' );
		$r = VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE loai=%s ORDER BY stt ASC LIMIT 1", 'Chi phí cơ sở' ) );
		if ( ! $r ) { return ''; }   // 🔴 KHÔNG tự tạo: hàm này bị gọi cả trong delete()
		VHCP_Meta::set( self::MK_COSO_CHUNG, (string) $r['ma_da'] );
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
		$ten = VHCP_Util::san( $ten );
		if ( '' === $ten ) { return VHCP_Util::err( 'Nhập tên đợt chi phí cơ sở' ); }
		$tu  = trim( (string) $tu );
		$den = trim( (string) $den );
		if ( '' !== $tu && '' !== $den && $den < $tu ) {
			return VHCP_Util::err( 'Ngày kết thúc không được trước ngày bắt đầu.' );
		}
		/* Ghim trạng thái "đơn chung" TRƯỚC khi thêm đơn mới — xem chốt ⚠️ ở trên. */
		if ( '' === self::ma_coso_chung() ) { VHCP_Meta::set( self::MK_COSO_CHUNG, '-' ); }
		$ma = VHCP_Util::uid( 'DA' );
		$wpdb->insert( VHCP_DB::t( 'da_index' ), array(
			'ma_da'      => $ma,
			'ten'        => $ten,
			'loai'       => 'Chi phí cơ sở',
			'trang_thai' => 'Đang làm',
			'ngay_tao'   => VHCP_Util::now_sql(),
			'nguoi_tao'  => (string) $nguoi,
		) );
		if ( '' !== $tu || '' !== $den ) { VHCP_Meta::set_json( 'daKy_' . $ma, array( 'tu' => $tu, 'den' => $den ) ); }
		VHCP_Log::log_action( array(
			'actor'  => (string) ( '' !== $nguoi ? $nguoi : VHCP_Auth::nguoi() ),
			'role'   => VHCP_Auth::vai_tro(),
			'action' => 'Lập đơn chi phí cơ sở',
			'target' => $ma,
			'detail' => $ten . ( ( '' !== $tu || '' !== $den ) ? ( ' · ' . $tu . ' → ' . $den ) : '' ),
		) );
		return VHCP_Util::ok( array( 'maDA' => $ma, 'ten' => $ten, 'loai' => 'Chi phí cơ sở', 'sheet' => '', 'trangThai' => 'Đang làm', 'url' => '', 'kyDA' => array( 'tu' => $tu, 'den' => $den ) ) );
	}

	/** ensureCoSoChung(): chi phí cơ sở kỹ thuật = 1 "sheet" CHUNG duy nhất, xuyên suốt. */
	public static function ensure_co_so_chung( $nguoi ) {
		global $wpdb;
		$ma_cu = self::ma_coso_chung();
		if ( '' !== $ma_cu ) {
			$r = self::find( $ma_cu );
			return VHCP_Util::ok( array(
				'maDA'      => $r['ma_da'],
				'ten'       => $r['ten'],
				'loai'      => 'Chi phí cơ sở',
				'sheet'     => '',
				'trangThai' => ( $r['trang_thai'] !== '' ? $r['trang_thai'] : 'Đang làm' ),
				'url'       => '',
			) );
		}
		$ma  = VHCP_Util::uid( 'DA' );
		$ten = 'Chi phí cơ sở (Kỹ thuật · chung)';
		$wpdb->insert( VHCP_DB::t( 'da_index' ), array(
			'ma_da'      => $ma,
			'ten'        => $ten,
			'loai'       => 'Chi phí cơ sở',
			'trang_thai' => 'Đang làm',
			'ngay_tao'   => VHCP_Util::now_sql(),
			'nguoi_tao'  => (string) $nguoi,
		) );
		VHCP_Meta::set( self::MK_COSO_CHUNG, $ma );
		return VHCP_Util::ok( array( 'maDA' => $ma, 'ten' => $ten, 'loai' => 'Chi phí cơ sở', 'sheet' => '', 'trangThai' => 'Đang làm', 'url' => '' ) );
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
		$dv_xem = VHCP_DonVi::xem_duoc();
		foreach ( self::all_with_lines() as $r ) {
			if ( null !== $dv_xem
				&& ! VHCP_DonVi::duoc_xem( VHCP_DonVi::cua_nguoi( isset( $r['nguoi_tao'] ) ? $r['nguoi_tao'] : '' ) ) ) { continue; }
			$ma_da = (string) $r['ma_da'];
			$hm_all = self::get_hm( $ma_da );
			/* Tiền của một "đơn": hạng mục có con thì tiền nằm ở CON, không có con thì ở chính
			   nó. Cộng cả hai là đếm hai lần — và đây là con số kế toán chuẩn bị tiền. */
			$con = array();
			foreach ( $r['lines'] as $x ) {
				$cap = trim( (string) $x['cap_cha'] );
				if ( '' !== $cap && '(Phát sinh)' !== $cap ) {
					$con[ $cap ] = ( isset( $con[ $cap ] ) ? $con[ $cap ] : 0 ) + VHCP_Util::num( $x['thuc_te'] );
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
					'nguoiTao'  => isset( $r['nguoi_tao'] ) ? (string) $r['nguoi_tao'] : '',
					'row'       => $row,
					'noiDung'   => $nd,
					'gian'      => trim( (string) $x['gian'] ),
					'hinhThuc'  => trim( (string) $x['hinh_thuc'] ),
					'duToan'    => VHCP_Util::num( $x['du_toan'] ),
					'thucTe'    => ( isset( $con[ $nd ] ) && $con[ $nd ] > 0 ) ? $con[ $nd ] : VHCP_Util::num( $x['thuc_te'] ),
					'hm'        => $h,
					'kyDA'      => self::get_ky_da( $ma_da ),
				);
			}
		}
		return VHCP_Util::ok( array( 'items' => $out ) );
	}

	public static function list_du_an() {
		$out = array();
		$sc_tong = VHCP_SoChi::tong_theo_du_an();   // 1 lệnh DB cho mọi dự án
		$dv_xem  = VHCP_DonVi::xem_duoc();
		/* ⚠️ HỎI MỘT LẦN, NGOÀI VÒNG LẶP. `la_coso_chung()` đọc meta mỗi lần gọi; gọi trong
		   vòng là thêm đúng một lệnh DB cho MỖI dự án, và `bench-queries.php` đỏ ngay. */
		$ma_chung = self::ma_coso_chung();
		foreach ( self::all_with_lines() as $r ) {
			/* Dự án neo theo NGƯỜI TẠO — xem chốt dài ở `don_vi_cua()`. */
			if ( null !== $dv_xem
				&& ! VHCP_DonVi::duoc_xem( VHCP_DonVi::cua_nguoi( isset( $r['nguoi_tao'] ) ? $r['nguoi_tao'] : '' ) ) ) { continue; }
			$lines = $r['lines'];
			$dt = 0; $tt = 0; $child = array();
			foreach ( $lines as $x ) {
				$cap = trim( (string) $x['cap_cha'] );
				if ( $cap !== '' && $cap !== '(Phát sinh)' ) { $child[ $cap ] = ( isset( $child[ $cap ] ) ? $child[ $cap ] : 0 ) + VHCP_Util::num( $x['thuc_te'] ); }
			}
			$cs_co = array();
			foreach ( $lines as $x ) {
				if ( ! self::is_real( $x ) ) { continue; }
				$nd  = trim( (string) $x['noi_dung'] );
				$cap = trim( (string) $x['cap_cha'] );
				$g   = trim( (string) $x['gian'] );
				if ( '' !== $g ) { $cs_co[ mb_strtolower( $g ) ] = 1; }
				if ( $cap === '' ) {
					$dt += VHCP_Util::num( $x['du_toan'] );
					if ( ! ( isset( $child[ $nd ] ) && $child[ $nd ] > 0 ) ) { $tt += VHCP_Util::num( $x['thuc_te'] ); }
				} else {
					$tt += VHCP_Util::num( $x['thuc_te'] );
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
				'ngayTao'    => VHCP_Util::fmt( $r['ngay_tao'] ),
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
		   dựng công phu ở `VHCP_DonVi` đều vô nghĩa ngay tại ô người ta gõ hằng ngày: chọn
		   nhầm một gian của bên kia là dòng chi rơi sang sổ của họ.

		   ⚠️ `coso_xem_duoc()` trả `null` nghĩa là XEM CẢ (Admin · Quản lý · Kế toán) — lúc ấy
		      phải bày đủ, không phải bày rỗng. */
		$coso = VHCP_DonVi::coso_xem_duoc();
		if ( null === $coso ) {
			$coso = array();
			foreach ( VHCP_Cfg::cfg_static()['coso'] as $x ) { $coso[] = $x['ten']; }
		}
		return VHCP_Util::ok( array( 'items' => array_reverse( $out ), 'coso' => $coso ) );
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
		$ten = VHCP_Util::san( $ten );
		if ( $ten === '' ) { return VHCP_Util::err( 'Tên trống' ); }
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		if ( self::la_coso_chung( $ma_da ) ) { return VHCP_Util::err( 'Sổ Chi phí cơ sở CHUNG không đổi tên — đơn cơ sở theo đợt thì đổi được' ); }
		$wpdb->update( VHCP_DB::t( 'da_index' ), array( 'ten' => $ten ), array( 'ma_da' => (string) $ma_da ) );
		return VHCP_Util::ok( array( 'ten' => $ten ) );
	}

	// ---------------------------------------------------------------- chi tiết

	public static function get_du_an( $ma_da ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }

		$lines = array();
		foreach ( self::lines_of( $ma_da ) as $r ) {
			if ( ! self::is_real( $r ) ) { continue; }
			$lines[] = array(
				'row'       => (int) $r['row_no'],
				'noiDung'   => (string) $r['noi_dung'],
				'duToan'    => VHCP_Util::num( $r['du_toan'] ),
				'thucTe'    => VHCP_Util::num( $r['thuc_te'] ),
				'soLuong'   => VHCP_Util::num( $r['so_luong'] ),
				'donGia'    => VHCP_Util::num( $r['don_gia'] ),
				'thanhTien' => VHCP_Util::num( $r['thanh_tien'] ),
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
			if ( in_array( $d['tt'], array( 'xin', 'duyet', 'ung' ), true ) ) { $da_xin += $d['soTien']; }
			if ( 'ung' === $d['tt'] ) { $da_chi += $d['soTien']; }
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
			$du_kien += self::tien_hm( $ma_da, (int) $r3['row_no'] );
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
		$sc_lines = VHCP_SoChi::theo_du_an( (string) $ma_da );
		if ( ! count( $sc_lines ) ) { $sc_lines = VHCP_SoChi::theo_du_an( (string) $f['ten'] ); }
		$sc_tien = 0; $sc_du_toan = 0;
		foreach ( $sc_lines as $x ) {
			$sc_tien    += VHCP_Util::num( $x['soTien'] );
			$sc_du_toan += VHCP_Util::num( $x['duToan'] );
		}

		$st  = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		$pay = self::get_pay( $ma_da );   // đọc MỘT lần, dùng cho cả 'pay' lẫn 'payTong'
		return VHCP_Util::ok( array(
			'maDA'            => (string) $ma_da,
			'ten'             => $f['ten'],
			'loai'            => $f['loai'],
			'trangThai'       => $st,
			'url'             => '',
			'isCoSo'          => ( $f['loai'] === 'Chi phí cơ sở' ),
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
			'qtDaGui'         => $qt_gui,
			'qtDaChot'        => $qt_chot,
			'duKienTU'        => $du_kien,
			'daXinTU'         => $da_xin,
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
		$o = VHCP_Meta::get_json( 'da_ndlist_v1', array() );
		return isset( $o[ $loai ] ) ? $o[ $loai ] : array();
	}

	private static function push_nd( $loai, $nd ) {
		$nd = trim( (string) $nd );
		if ( $nd === '' ) { return; }
		$o = VHCP_Meta::get_json( 'da_ndlist_v1', array() );
		$a = isset( $o[ $loai ] ) ? (array) $o[ $loai ] : array();
		$low = mb_strtolower( $nd );
		foreach ( $a as $x ) { if ( mb_strtolower( (string) $x ) === $low ) { return; } }
		array_unshift( $a, $nd );
		if ( count( $a ) > 300 ) { $a = array_slice( $a, 0, 300 ); }
		$o[ $loai ] = $a;
		VHCP_Meta::set_json( 'da_ndlist_v1', $o );
	}

	// ---------------------------------------------------------------- kế toán chi tiền

	public static function get_pay( $ma_da ) {
		return VHCP_Meta::get_json( 'daPay_' . $ma_da, array() );
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
		return VHCP_Util::num( VHCP_Meta::get( 'daDuToan_' . $ma_da, 0 ) );
	}

	/**
	 * THỜI GIAN SETUP — anh Thắng 10/09/2026: *"Bổ sung thêm thời gian setup (Từ ngày đến
	 * ngày)"*, đúng nguyên lý *"Đơn theo thời gian, chứ không theo tuần"* anh nêu từ đầu.
	 *
	 * ⚠️ KHÔNG ÉP PHẢI CÓ. Dự án đang chạy dở chưa ai gõ khoảng ngày; bắt buộc là mọi dự án cũ
	 *    đỏ lên một lỗi mà không ai gây ra.
	 */
	public static function get_ky_da( $ma_da ) {
		$o = VHCP_Meta::get_json( 'daKy_' . $ma_da, array() );
		return array(
			'tu'  => isset( $o['tu'] ) ? (string) $o['tu'] : '',
			'den' => isset( $o['den'] ) ? (string) $o['den'] : '',
		);
	}

	public static function set_ky_da( $ma_da, $tu, $den, $nguoi = '' ) {
		if ( ! self::find( $ma_da ) ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$tu  = trim( (string) $tu );
		$den = trim( (string) $den );
		/* 🔴 NGÀY KẾT THÚC KHÔNG ĐƯỢC TRƯỚC NGÀY BẮT ĐẦU. Khoảng ngày ngược làm mọi phép "dự án
		   này kéo dài bao lâu" ra số âm, và không có gì trên màn nói vì sao. So bằng chuỗi
		   yyyy-mm-dd là đúng thứ tự thời gian, khỏi phải dựng đối tượng ngày. */
		if ( '' !== $tu && '' !== $den && $den < $tu ) {
			return VHCP_Util::err( 'Ngày kết thúc không được trước ngày bắt đầu.' );
		}
		VHCP_Meta::set_json( 'daKy_' . $ma_da, array( 'tu' => $tu, 'den' => $den ) );
		VHCP_Log::log_action( array(
			'actor'  => (string) ( '' !== $nguoi ? $nguoi : VHCP_Auth::nguoi() ),
			'role'   => VHCP_Auth::vai_tro(),
			'action' => 'Đặt thời gian setup dự án',
			'target' => (string) $ma_da,
			'detail' => $tu . ' → ' . $den,
		) );
		return VHCP_Util::ok( array( 'ky' => array( 'tu' => $tu, 'den' => $den ) ) );
	}

	public static function set_du_toan_da( $ma_da, $so, $nguoi = '' ) {
		if ( ! self::find( $ma_da ) ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$n = VHCP_Util::num( $so );
		/* 🔴 SỐ ÂM LÀ VÔ NGHĨA, và nó lặng lẽ làm phần "còn dự phòng" phình ra. Chối thẳng. */
		if ( $n < 0 ) { return VHCP_Util::err( 'Tổng dự toán không được âm.' ); }
		VHCP_Meta::set( 'daDuToan_' . $ma_da, $n );
		VHCP_Log::log_action( array(
			'actor'  => (string) ( '' !== $nguoi ? $nguoi : VHCP_Auth::nguoi() ),
			'role'   => VHCP_Auth::vai_tro(),
			'action' => 'Đặt tổng dự toán dự án',
			'target' => (string) $ma_da,
			'detail' => (string) $n,
		) );
		return VHCP_Util::ok( array( 'duToanDA' => $n ) );
	}

	public static function approve_date( $ma_da ) {
		return (string) VHCP_Meta::get( 'daApp_' . $ma_da, '' );
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
		foreach ( self::pay_ds( $p, $phase, $loai ) as $x ) { $t += VHCP_Util::num( isset( $x['amount'] ) ? $x['amount'] : 0 ); }
		return $t;
	}

	public static function confirm_pay( $ma_da, $phase, $loai, $amount, $nguoi, $ghi_chu = '' ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		if ( ! in_array( (string) $phase, array( 'tamUng', 'quyetToan' ), true ) ) { return VHCP_Util::err( 'Giai đoạn không hợp lệ' ); }
		if ( ! in_array( (string) $loai, array( 'tu', 'tt' ), true ) ) { return VHCP_Util::err( 'Loại chi không hợp lệ' ); }
		$st = (string) $f['trang_thai'];
		if ( $st !== 'Đã duyệt' && $st !== 'Đã đóng' ) { return VHCP_Util::err( 'Chỉ chi tiền khi dự án đã kế toán duyệt tạm ứng' ); }
		/* 🔴 SỐ 0 KHÔNG PHẢI MỘT LẦN CHI. Ghi vào là danh sách có một dòng 0đ — nhìn thì như đã
		   chi, cộng vào thì không đổi gì, và người đọc sổ mất một lượt đi tìm xem nó là cái gì. */
		$so = VHCP_Util::num( $amount );
		if ( $so <= 0 ) { return VHCP_Util::err( 'Số tiền phải lớn hơn 0.' ); }

		$p  = self::get_pay( $ma_da );
		$ds = self::pay_ds( $p, $phase, $loai );
		$ds[] = array(
			'done'   => true,
			'amount' => $so,
			'date'   => VHCP_Util::now()->format( 'd/m/Y H:i' ),
			'by'     => (string) $nguoi,
			'ghiChu' => trim( (string) $ghi_chu ),
		);
		if ( ! isset( $p[ $phase ] ) || ! is_array( $p[ $phase ] ) ) { $p[ $phase ] = array(); }
		$p[ $phase ][ $loai ] = $ds;
		VHCP_Meta::set_json( 'daPay_' . $ma_da, $p );
		return VHCP_Util::ok( array( 'pay' => $p ) );
	}

	/**
	 * Bỏ MỘT lần chi. `$idx` là vị trí trong danh sách; để trống thì bỏ lần CUỐI.
	 *
	 * ⚠️ BỎ LẦN CUỐI, KHÔNG PHẢI BỎ SẠCH. Bản trước xoá cả ô — nay ô ấy có thể chứa năm lần
	 *    ứng, nên xoá sạch là mất bốn lần không ai định đụng tới. Hoàn tác là "gỡ cái vừa ghi",
	 *    đúng nghĩa cái nút ↩ trên màn.
	 */
	public static function unconfirm_pay( $ma_da, $phase, $loai, $idx = null ) {
		if ( ! self::find( $ma_da ) ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$p  = self::get_pay( $ma_da );
		$ds = self::pay_ds( $p, $phase, $loai );
		if ( ! $ds ) { return VHCP_Util::err( 'Chưa có lần chi nào để hoàn tác.' ); }
		$i = ( null === $idx || '' === $idx ) ? ( count( $ds ) - 1 ) : (int) $idx;
		if ( $i < 0 || $i >= count( $ds ) ) { return VHCP_Util::err( 'Không có lần chi thứ ' . ( (int) $idx + 1 ) . '.' ); }
		array_splice( $ds, $i, 1 );
		if ( ! isset( $p[ $phase ] ) || ! is_array( $p[ $phase ] ) ) { $p[ $phase ] = array(); }
		if ( $ds ) { $p[ $phase ][ $loai ] = $ds; }
		else { unset( $p[ $phase ][ $loai ] ); }
		VHCP_Meta::set_json( 'daPay_' . $ma_da, $p );
		return VHCP_Util::ok( array( 'pay' => $p ) );
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
		$o = VHCP_Meta::get_json( 'daHM_' . $ma_da, array() );
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
		$t = VHCP_DB::t( 'da_line' );
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
		$moi['moc'][ $moi['tt'] ] = VHCP_Util::now()->format( 'd/m/Y H:i' ) . ' · ' . VHCP_Auth::nguoi();
		$o[ $k ] = $moi;
		VHCP_Meta::set_json( 'daHM_' . $ma_da, $o );
		VHCP_Log::log_action( array(
			'actor'  => VHCP_Auth::nguoi(),
			'role'   => VHCP_Auth::vai_tro(),
			'action' => 'Đổi trạng thái hạng mục dự án',
			'target' => (string) $ma_da . '#' . $k,
			'detail' => $cu['tt'] . ' → ' . $moi['tt'] . ( $moi['dot'] ? ( ' · đợt ' . $moi['dot'] ) : '' ),
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
		$o = VHCP_Meta::get_json( self::dot_meta_( $loai ) . $ma_da, array() );
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
			'soTien' => isset( $x['soTien'] ) ? VHCP_Util::num( $x['soTien'] ) : 0,
			'unc'    => isset( $x['unc'] ) ? (string) $x['unc'] : '',
			'lyDo'   => isset( $x['lyDo'] ) ? (string) $x['lyDo'] : '',
			'lich'   => isset( $x['lich'] ) && is_array( $x['lich'] ) ? array_values( $x['lich'] ) : array(),
			'moc'    => isset( $x['moc'] ) && is_array( $x['moc'] ) ? $x['moc'] : array(),
		);
	}

	/** Mọi lệnh của một dự án, đợt nhỏ trước. */
	public static function ds_dot( $ma_da, $loai = 'tu' ) {
		$ra = array();
		foreach ( array_keys( self::get_dot( $ma_da, $loai ) ) as $k ) {
			$d = self::dot_cua( $ma_da, $k, $loai );
			if ( $d ) { $ra[] = $d; }
		}
		usort( $ra, function ( $a, $b ) { return $a['dot'] - $b['dot']; } );
		return $ra;
	}

	private static function dot_ghi_( $ma_da, $dot, $sua, $viec, $loai = 'tu' ) {
		$o  = self::get_dot( $ma_da, $loai );
		$k  = (string) (int) $dot;
		$cu = self::dot_cua( $ma_da, $dot, $loai );
		$moi = array_merge( $cu ? $cu : array( 'dot' => (int) $dot, 'tt' => 'xin', 'rows' => array(),
			'soTien' => 0, 'unc' => '', 'lyDo' => '', 'lich' => array(), 'moc' => array() ), $sua );
		$moi['moc'] = ( $cu && $cu['moc'] ) ? $cu['moc'] : array();
		$moi['moc'][ $moi['tt'] ] = VHCP_Util::now()->format( 'd/m/Y H:i' ) . ' · ' . VHCP_Auth::nguoi();
		$o[ $k ] = $moi;
		VHCP_Meta::set_json( self::dot_meta_( $loai ) . $ma_da, $o );
		VHCP_Log::log_action( array(
			'actor'  => VHCP_Auth::nguoi(),
			'role'   => VHCP_Auth::vai_tro(),
			'action' => (string) $viec,
			'target' => (string) $ma_da . ' · đợt ' . $k,
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
				$tu_than = VHCP_Util::num( $l['thuc_te'] );
			}
		}
		if ( '' === $nd ) { return 0; }
		$con = 0; $co_con = false;
		foreach ( $lines as $l ) {
			if ( trim( (string) $l['cap_cha'] ) === $nd ) { $co_con = true; $con += VHCP_Util::num( $l['thuc_te'] ); }
		}
		return $co_con ? $con : $tu_than;
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
		if ( ! self::find( $ma_da ) ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$rows = array_values( array_unique( array_map( 'intval', (array) $rows ) ) );
		if ( ! $rows ) { return VHCP_Util::err( 'Chưa tích hạng mục nào để xin tạm ứng.' ); }

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
				return VHCP_Util::err( 'Dòng ' . $r . ' không phải hạng mục lớn của dự án này.' );
			}
			if ( self::hm_la_ncc( $ma_da, $r ) ) {
				return VHCP_Util::err( '"' . $lon[ $r ] . '" là khoản kế toán trả thẳng nhà cung cấp — không xin tạm ứng.' );
			}
			$h = self::hm_cua( $ma_da, $r );
			if ( ! in_array( $h['tt'], array( 'nhap', 'tra' ), true ) ) {
				return VHCP_Util::err( '"' . $lon[ $r ] . '" đã nằm trong một lệnh tạm ứng rồi.' );
			}
			$nhan[] = $r;
			$tong  += self::tien_hm( $ma_da, $r );
		}

		$ds  = self::get_dot( $ma_da );
		$dot = 0;
		foreach ( array_keys( $ds ) as $k ) { $dot = max( $dot, (int) $k ); }
		$dot++;

		/* Lịch đi nhận tiền: dòng thiếu ngày thì bỏ — một đợt không ngày thì kế toán chuẩn bị
		   tiền vào hôm nào? */
		$lc = array(); $lan = 0;
		foreach ( (array) $lich as $x ) {
			$x = (array) $x;
			$ngay = isset( $x['ngay'] ) ? trim( (string) $x['ngay'] ) : '';
			if ( '' === $ngay ) { continue; }
			$lan++;
			$lc[] = array( 'lan' => $lan, 'ngay' => $ngay,
				'soTien' => VHCP_Util::num( isset( $x['soTien'] ) ? $x['soTien'] : 0 ) );
		}

		$moi = self::dot_ghi_( $ma_da, $dot, array(
			'tt' => 'xin', 'rows' => $nhan, 'soTien' => $tong, 'lich' => $lc,
			'lyDo' => trim( (string) $ghi_chu ), 'unc' => '',
		), 'Xin tạm ứng cho dự án' );
		foreach ( $nhan as $r ) { self::hm_ghi_( $ma_da, $r, array( 'tt' => 'xin', 'dot' => $dot ) ); }
		return VHCP_Util::ok( array( 'dot' => $moi, 'soTien' => $tong, 'so' => count( $nhan ) ) );
	}

	/**
	 * Đổi trạng thái CẢ LỆNH — duyệt / trả lại / cấp tiền. Mọi hạng mục trong lệnh đi theo.
	 *
	 * 🔴 CHỐT THEO VAI. Ẩn nút trên màn chỉ là tiện tay; ai gọi thẳng API vẫn phải bị chặn,
	 *    nếu không thì nhân viên tự duyệt rồi tự cấp tạm ứng cho chính mình.
	 */
	public static function dat_tt_dot( $ma_da, $dot, $tt, $them = array() ) {
		if ( ! self::find( $ma_da ) ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$tt = (string) $tt;
		if ( ! in_array( $tt, self::TT_DOT, true ) ) { return VHCP_Util::err( 'Trạng thái không hợp lệ' ); }
		$d = self::dot_cua( $ma_da, $dot );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy lệnh tạm ứng đợt ' . (int) $dot ); }
		$them = (array) $them;
		$vai  = VHCP_Auth::vai_tro();
		$duyet_duoc = in_array( $vai, array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' ), true );
		$ke_toan    = in_array( $vai, array( 'Admin', 'Kế toán cá nhân', 'Kế toán NCC' ), true );

		if ( in_array( $tt, array( 'duyet', 'tra' ), true ) && ! $duyet_duoc ) {
			return VHCP_Util::err( 'Chỉ quản lý hoặc kế toán duyệt / trả lại được.' );
		}
		if ( 'ung' === $tt && ! $ke_toan ) { return VHCP_Util::err( 'Chỉ kế toán cấp tạm ứng được.' ); }
		if ( 'ung' === $d['tt'] ) { return VHCP_Util::err( 'Lệnh đợt ' . $d['dot'] . ' đã cấp tiền rồi.' ); }
		if ( 'duyet' === $tt && 'xin' !== $d['tt'] ) { return VHCP_Util::err( 'Chỉ duyệt được lệnh đang xin tạm ứng.' ); }
		if ( 'ung' === $tt && 'duyet' !== $d['tt'] ) { return VHCP_Util::err( 'Chỉ cấp tiền cho lệnh đã duyệt.' ); }
		if ( 'tra' === $tt && ! in_array( $d['tt'], array( 'xin', 'duyet' ), true ) ) {
			return VHCP_Util::err( 'Lệnh này không ở bước trả lại được.' );
		}

		$sua = array( 'tt' => $tt );
		if ( isset( $them['unc'] ) ) { $sua['unc'] = trim( (string) $them['unc'] ); }
		if ( isset( $them['lyDo'] ) ) { $sua['lyDo'] = trim( (string) $them['lyDo'] ); }
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
		return VHCP_Util::ok( array( 'dot' => $moi ) );
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
		if ( ! self::find( $ma_da ) ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$rows = array_values( array_unique( array_map( 'intval', (array) $rows ) ) );
		if ( ! $rows ) { return VHCP_Util::err( 'Chưa tích hạng mục nào để gửi quyết toán.' ); }

		$lon = array();
		foreach ( self::lines_of( $ma_da ) as $l ) {
			if ( ! self::is_real( $l ) ) { continue; }
			if ( trim( (string) $l['cap_cha'] ) !== '' ) { continue; }
			$lon[ (int) $l['row_no'] ] = trim( (string) $l['noi_dung'] );
		}

		$nhan = array(); $tong = 0;
		foreach ( $rows as $r ) {
			if ( ! isset( $lon[ $r ] ) ) {
				return VHCP_Util::err( 'Dòng ' . $r . ' không phải hạng mục lớn của dự án này.' );
			}
			$h = self::hm_cua( $ma_da, $r );
			if ( 'xong' !== $h['tt'] ) {
				return VHCP_Util::err( '"' . $lon[ $r ] . '" chưa chốt hoàn thành — chốt hoá đơn xong mới gửi quyết toán được.' );
			}
			if ( $h['qtDot'] > 0 ) {
				return VHCP_Util::err( '"' . $lon[ $r ] . '" đã nằm trong một lệnh quyết toán rồi.' );
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
		return VHCP_Util::ok( array( 'dot' => $moi, 'soTien' => $tong, 'so' => count( $nhan ) ) );
	}

	/**
	 * Kế toán CHỐT hoặc TRẢ LẠI một lệnh quyết toán.
	 *
	 * 🔴 CHỐT SỔ LÀ VIỆC CỦA KẾ TOÁN. Nhân viên tự chốt là tự nói "khoản của tôi đã đối chiếu
	 *    xong" — mà đối chiếu là việc của người giữ sổ 141.
	 */
	public static function dat_tt_qt( $ma_da, $dot, $tt, $them = array() ) {
		if ( ! self::find( $ma_da ) ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$tt = (string) $tt;
		if ( ! in_array( $tt, array( 'xong', 'tra' ), true ) ) { return VHCP_Util::err( 'Trạng thái không hợp lệ' ); }
		$d = self::dot_cua( $ma_da, $dot, 'qt' );
		if ( ! $d ) { return VHCP_Util::err( 'Không tìm thấy lệnh quyết toán đợt ' . (int) $dot ); }
		$vai = VHCP_Auth::vai_tro();
		if ( ! in_array( $vai, array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' ), true ) ) {
			return VHCP_Util::err( 'Chỉ quản lý hoặc kế toán chốt / trả lại quyết toán được.' );
		}
		if ( 'xong' === $d['tt'] ) { return VHCP_Util::err( 'Lệnh quyết toán đợt ' . $d['dot'] . ' đã chốt sổ rồi.' ); }

		$sua = array( 'tt' => $tt );
		if ( isset( $them['lyDo'] ) ) { $sua['lyDo'] = trim( (string) $them['lyDo'] ); }
		$moi = self::dot_ghi_( $ma_da, $dot, $sua,
			'tra' === $tt ? 'Trả lại lệnh quyết toán dự án' : 'Chốt quyết toán dự án', 'qt' );

		/* TRẢ LẠI thì GỠ cờ quyết toán khỏi hạng mục — giữ lại thì nhân viên sửa xong tích lại
		   sẽ bị chối "đã nằm trong một lệnh rồi", mà lệnh ấy đã bị trả. */
		if ( 'tra' === $tt ) {
			foreach ( $d['rows'] as $r ) { self::hm_ghi_( $ma_da, $r, array( 'qtDot' => 0 ) ); }
		}
		return VHCP_Util::ok( array( 'dot' => $moi ) );
	}

	/**
	 * MỌI LỆNH TẠM ỨNG CỦA MỌI DỰ ÁN — nuôi bảng Duyệt của kế toán.
	 *
	 * ⚠️ Tôn trọng tầm nhìn đơn vị như `list_du_an()`: dự án neo theo NGƯỜI TẠO.
	 */
	public static function list_lenh_da( $loai = 'tu' ) {
		$loai   = ( 'qt' === $loai ) ? 'qt' : 'tu';
		$out    = array();
		$dv_xem = VHCP_DonVi::xem_duoc();
		foreach ( self::all_with_lines() as $r ) {
			if ( null !== $dv_xem
				&& ! VHCP_DonVi::duoc_xem( VHCP_DonVi::cua_nguoi( isset( $r['nguoi_tao'] ) ? $r['nguoi_tao'] : '' ) ) ) { continue; }
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
					'nguoiTao' => isset( $r['nguoi_tao'] ) ? (string) $r['nguoi_tao'] : '',
					'dot'      => $d['dot'],
					'tt'       => $d['tt'],
					'rows'     => $d['rows'],
					'tenHM'    => $ten,
					'soTien'   => $d['soTien'],
					'unc'      => $d['unc'],
					'lyDo'     => $d['lyDo'],
					'lich'     => $d['lich'],
					'moc'      => $d['moc'],
					'kyDA'     => self::get_ky_da( $ma_da ),
				);
			}
		}
		return VHCP_Util::ok( array( 'items' => $out ) );
	}

	/**
	 * Đổi trạng thái một hạng mục.
	 *
	 * 🔴 CHỐT THEO VAI, KHÔNG THEO NÚT TRÊN MÀN. Màn ẩn nút là tiện tay; ai gọi thẳng API vẫn
	 *    phải bị chặn — nếu không thì nhân viên tự duyệt và tự cấp tạm ứng cho chính mình.
	 */
	public static function dat_hm( $ma_da, $row, $tt, $them = array() ) {
		if ( ! self::find( $ma_da ) ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$tt = (string) $tt;
		if ( ! in_array( $tt, self::TT_HM, true ) ) { return VHCP_Util::err( 'Trạng thái không hợp lệ' ); }
		$them = (array) $them;
		$vai  = VHCP_Auth::vai_tro();
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
			return VHCP_Util::err( 'Hạng mục đã chốt là chi thực tế — không đổi được nữa.' );
		}
		if ( 'xong' === $cu['tt'] && 'nhap' === $tt && ! $ke_toan ) {
			return VHCP_Util::err( 'Chỉ kế toán mở lại được hạng mục đã chốt.' );
		}
		if ( in_array( $tt, array( 'duyet', 'tra' ), true ) && ! $duyet_duoc ) {
			return VHCP_Util::err( 'Chỉ quản lý hoặc kế toán duyệt / trả lại được.' );
		}
		if ( 'ung' === $tt && ! $ke_toan ) {
			return VHCP_Util::err( 'Chỉ kế toán cấp tạm ứng được.' );
		}
		/* 🔴 ĐƠN NCC THÌ CHỈ KẾ TOÁN ĐƯỢC ĐÁNH DẤU ĐÃ CHI / KHOÁ. Với đơn tạm ứng, nhân viên tự
		   tích hoàn thành là đúng ý anh Thắng (*"tích hoàn thành và bổ sung hoá đơn nó sẽ khoá
		   đơn đó lại"*) — họ là người cầm tiền đi mua. Đơn NCC thì ngược hẳn: tiền do kế toán
		   trả, nên chỉ kế toán mới biết đã đi hay chưa. Để nhân viên khoá là họ chốt hộ một
		   khoản chính họ không trả, mà từ 'nhap' đi thẳng tới 'xong' là qua mặt cả chuỗi. */
		if ( $la_ncc && in_array( $tt, array( 'xong', 'ung' ), true ) && ! $ke_toan ) {
			return VHCP_Util::err( 'Hạng mục này kế toán trả thẳng nhà cung cấp — chỉ kế toán xác nhận đã chi và khoá đơn.' );
		}
		if ( $la_ncc && 'xin' === $tt ) {
			return VHCP_Util::err( 'Hạng mục này kế toán trả thẳng nhà cung cấp — không xin tạm ứng. Kế toán tích xác nhận đã chi.' );
		}
		/* 🔴 XIN / DUYỆT / CẤP TIỀN CỦA ĐƠN TẠM ỨNG NAY ĐI THEO LỆNH, KHÔNG THEO TỪNG HẠNG MỤC.
		   Anh Thắng: *"Trong 1 đơn chứ, trong 1 đơn mà nhiều lệnh tạm ứng, đơn nào bấm xin thì
		   nó tổng tổng tạm ứng cần xin"*.
		   Để hở đường cũ là mở ra một lối thứ hai: hạng mục nhảy sang 'xin' mà KHÔNG thuộc lệnh
		   nào, nên bảng Duyệt của kế toán không bao giờ thấy nó — nhân viên tưởng đã gửi, kế
		   toán không có gì để duyệt, và không ai biết vì sao.
		   ⚠️ Đơn 🏢 TRỰC TIẾP thì vẫn đi lối riêng: nó không có lệnh tạm ứng nào cả. */
		if ( ! $la_ncc && in_array( $tt, array( 'xin', 'duyet', 'ung' ), true ) ) {
			return VHCP_Util::err( 'Bước này nay đi theo LỆNH tạm ứng của cả dự án — '
				. 'tích các hạng mục rồi bấm "Xin tạm ứng" một lần.' );
		}
		/* Đơn NCC: kế toán đi thẳng tới "đã chi" hoặc "khoá", không cần chuỗi xin → duyệt. */
		$bo_qua_chuoi = ( $la_ncc && $ke_toan );
		if ( ! $bo_qua_chuoi ) {
			if ( 'xin' === $tt && ! in_array( $cu['tt'], array( 'nhap', 'tra' ), true ) ) {
				return VHCP_Util::err( 'Hạng mục này đã qua bước xin tạm ứng rồi.' );
			}
			if ( 'duyet' === $tt && 'xin' !== $cu['tt'] ) {
				return VHCP_Util::err( 'Chỉ duyệt được hạng mục đang xin tạm ứng.' );
			}
			if ( 'ung' === $tt && 'duyet' !== $cu['tt'] ) {
				return VHCP_Util::err( 'Chỉ cấp tạm ứng cho hạng mục đã duyệt.' );
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
					return VHCP_Util::err( 'Phải đính ít nhất một chứng từ (uỷ nhiệm chi hoặc hoá đơn) trước khi khoá đơn.' );
				}
			} elseif ( '' === $hd ) {
				return VHCP_Util::err( 'Phải đính hoá đơn trước khi chốt hoàn thành.' );
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
					'soTien' => VHCP_Util::num( isset( $x['soTien'] ) ? $x['soTien'] : 0 ),
				);
			}
			$sua['lich'] = $ds;
		}
		if ( 'nhap' === $tt ) { $sua['dot'] = 0; }
		$moi = self::hm_ghi_( $ma_da, $row, $sua );
		return VHCP_Util::ok( array( 'hm' => $moi ) );
	}

	// ---------------------------------------------------------------- dòng hạng mục

	private static function line_data( $rec ) {
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? $rec[ $k ] : null; };
		$sl  = VHCP_Util::num( $g( 'soLuong' ) );
		$dg  = VHCP_Util::num( $g( 'donGia' ) );
		$cap = VHCP_Util::st( $g( 'capCha' ) );
		// Gắn mã tài khoản theo LOẠI CHI PHÍ ngay lúc nhập (giống sổ chi phí).
		$loai_cp = VHCP_Util::st( $g( 'loaiCp' ) );
		// Gian hàng đóng vai "cơ sở" ở mảng kỹ thuật -> mã theo mảng kinh doanh của gian đó.
		$tk      = VHCP_Cfg::resolve_tk( $loai_cp, VHCP_Util::st( $g( 'hinhThuc' ) ), array( 'tkNo' => VHCP_Util::st( $g( 'tkNo' ) ), 'tkCo' => VHCP_Util::st( $g( 'tkCo' ) ), 'maDt' => VHCP_Util::st( $g( 'maDt' ) ) ), VHCP_Util::st( $g( 'gian' ) ) );
		return array(
			'loai_cp'    => $loai_cp,
			'tk_no'      => $loai_cp !== '' ? $tk['tk_no'] : '',
			'tk_co'      => $loai_cp !== '' ? $tk['tk_co'] : '',
			'ma_dt'      => $loai_cp !== '' ? $tk['ma_dt'] : '',
			'noi_dung'   => VHCP_Util::st( $g( 'noiDung' ) ),
			'du_toan'    => ( $cap === '' ) ? VHCP_Util::num( $g( 'duToan' ) ) : 0,   // chỉ hạng mục lớn có dự toán
			'thuc_te'    => VHCP_Util::num( $g( 'thucTe' ) ),
			'so_luong'   => $sl,
			'don_gia'    => $dg,
			'thanh_tien' => $sl * $dg,
			'vat'        => VHCP_Util::st( $g( 'vat' ) ),
			'anh'        => VHCP_Util::st( $g( 'anh' ) ),
			'gian'       => VHCP_Util::st( $g( 'gian' ) ),
			'note'       => VHCP_Util::st( $g( 'note' ) ),
			'cap_cha'    => $cap,
			'hinh_thuc'  => VHCP_Util::st( $g( 'hinhThuc' ) ),
			'ho_so'      => VHCP_Util::st( $g( 'hoSo' ) ),
		);
	}

	public static function add_line( $ma_da, $rec ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		// Dự án chi trực tiếp: chỉ ĐÃ ĐÓNG mới khóa. Trước đây còn chặn cả trạng thái
		// "Chờ kế toán duyệt" của luồng duyệt đã bỏ, làm gian cũ không nhận được dữ liệu.
		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		if ( $st === 'Đã đóng' ) {
			return VHCP_Util::err( 'Dự án đã đóng — bấm "Mở lại" rồi nhập' );
		}
		$data           = self::line_data( $rec );
		$data['ma_da']  = (string) $ma_da;
		$data['row_no'] = self::next_row( $ma_da );
		$wpdb->insert( VHCP_DB::t( 'da_line' ), $data );
		self::push_nd( $f['loai'], $data['noi_dung'] );
		return VHCP_Util::ok();
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
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		if ( 'Đã đóng' === $st ) { return VHCP_Util::err( 'Dự án đã đóng — bấm "Mở lại" rồi sửa' ); }
		$row = (int) $row;
		$t   = VHCP_DB::t( 'da_line' );
		$cu  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s AND row_no=%d", (string) $ma_da, $row ), ARRAY_A );
		if ( ! $cu ) { return VHCP_Util::err( 'Không tìm thấy dòng ' . $row ); }

		$_c = self::loi_hang_muc_da_chot_( $ma_da, $row, 'đổi chứng từ' );
		if ( '' !== $_c ) { return VHCP_Util::err( $_c ); }

		$moi = $url;
		if ( $cong_them && '' !== $url ) {
			$co = trim( (string) $cu[ $cot ] );
			$ds = preg_split( '/\s+/u', $co, -1, PREG_SPLIT_NO_EMPTY );
			if ( ! in_array( $url, $ds, true ) ) { $ds[] = $url; }
			$moi = implode( "\n", $ds );
		}
		$wpdb->update( $t, array( $cot => $moi ), array( 'ma_da' => (string) $ma_da, 'row_no' => $row ) );
		VHCP_Log::log_action( array(
			'actor'  => VHCP_Auth::nguoi(),
			'role'   => VHCP_Auth::vai_tro(),
			'action' => (string) $viec,
			'target' => (string) $ma_da . '#' . $row,
			'detail' => trim( (string) $cu['noi_dung'] ) . ( '' !== $url ? ( ' — ' . $url ) : ' — gỡ' ),
		) );
		$ra = array( 'row' => $row );
		$ra[ 'anh' === $cot ? 'anh' : 'hoSo' ] = $moi;
		return VHCP_Util::ok( $ra );
	}

	public static function update_line( $ma_da, $row, $rec ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		if ( $st === 'Đã đóng' ) { return VHCP_Util::err( 'Dự án đã đóng — bấm "Mở lại" rồi sửa' ); }
		$row = (int) $row;
		/* ⚠️ SỬA CHẶN NHƯ XOÁ. Anh Thắng nói *"không cho xoá dòng"*, nhưng để hở nút sửa thì gõ
		   tiền về 0 là xoá trá hình, chỉ khác cái tên. */
		$_c = self::loi_hang_muc_da_chot_( $ma_da, $row, 'sửa dòng' );
		if ( '' !== $_c ) { return VHCP_Util::err( $_c ); }
		if ( $row < self::DATA_ROW ) { return VHCP_Util::err( 'Dòng không hợp lệ' ); }
		$t   = VHCP_DB::t( 'da_line' );
		$cur = VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s AND row_no=%d", (string) $ma_da, $row ) );
		if ( ! $cur ) { return VHCP_Util::err( 'Dòng không hợp lệ' ); }
		$old_name = trim( (string) $cur['noi_dung'] );
		$data     = self::line_data( $rec );
		$wpdb->update( $t, $data, array( 'ma_da' => (string) $ma_da, 'row_no' => $row ) );
		if ( $data['cap_cha'] === '' && $old_name !== '' && $old_name !== $data['noi_dung'] ) {
			self::relink_children( $ma_da, $old_name, $data['noi_dung'] );   // hạng mục lớn đổi tên -> cập nhật mục con
		}
		self::push_nd( $f['loai'], $data['noi_dung'] );
		return VHCP_Util::ok();
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
		if ( '' === $ma_da ) { return VHCP_Util::ok( array( 'items' => array() ) ); }
		$limit = (int) $limit;
		if ( $limit <= 0 || $limit > 300 ) { $limit = 50; }
		$f   = self::find( $ma_da );
		$ten = $f ? trim( (string) $f['ten'] ) : '';
		$t   = VHCP_DB::t( 'log' );

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
			$tg = VHCP_Util::fmt_dt( $r['tg'] );
			if ( '' !== $tg && VHCP_Util::ngay_vo_ly( substr( $tg, 0, 10 ) ) ) { $tg = ''; }
			$items[] = array(
				'tg'       => $tg,
				'nguoi'    => (string) $r['nguoi'],
				'vaiTro'   => (string) $r['vai_tro'],
				'hanhDong' => (string) $r['hanh_dong'],
				'chiTiet'  => (string) $r['chi_tiet'],
			);
		}
		return VHCP_Util::ok( array( 'items' => $items ) );
	}

	public static function loi_hang_muc_da_chot_( $ma_da, $row, $viec = 'sửa' ) {
		global $wpdb;
		$t   = VHCP_DB::t( 'da_line' );
		$cur = VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s AND row_no=%d", (string) $ma_da, (int) $row ) );
		if ( ! $cur ) { return ''; }   // dòng không có thật thì để lối gọi tự báo
		$cap = trim( (string) $cur['cap_cha'] );
		$khoa_row = ( '' === $cap ) ? (int) $row : 0;
		if ( ! $khoa_row && '(Phát sinh)' !== $cap ) {
			foreach ( self::lines_of( $ma_da ) as $l ) {
				if ( trim( (string) $l['cap_cha'] ) === '' && trim( (string) $l['noi_dung'] ) === $cap ) {
					$khoa_row = (int) $l['row_no'];
				}
			}
		}
		if ( ! $khoa_row || ! self::hm_khoa( $ma_da, $khoa_row ) ) { return ''; }
		$h = self::hm_cua( $ma_da, $khoa_row );
		return 'Hạng mục đã chốt là chi thực tế — không ' . $viec . ' được nữa'
			. ( $h['qtDot'] > 0 ? ( ' (đã gửi quyết toán đợt ' . $h['qtDot'] . ')' ) : '' )
			. '. Kế toán bấm "🔓 Mở lại" thì mới đụng được.';
	}

	public static function delete_line( $ma_da, $row ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		$st = (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' );
		if ( $st === 'Đã đóng' ) { return VHCP_Util::err( 'Dự án đã đóng — bấm "Mở lại" rồi xóa' ); }
		$row = (int) $row;
		if ( $row < self::DATA_ROW ) { return VHCP_Util::err( 'Dòng không hợp lệ' ); }
		$t   = VHCP_DB::t( 'da_line' );
		$cur = VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s AND row_no=%d", (string) $ma_da, $row ) );
		if ( ! $cur ) { return VHCP_Util::err( 'Dòng không hợp lệ' ); }
		$_c = self::loi_hang_muc_da_chot_( $ma_da, $row, 'xoá dòng' );
		if ( '' !== $_c ) { return VHCP_Util::err( $_c ); }
		$nm  = trim( (string) $cur['noi_dung'] );
		$cap = trim( (string) $cur['cap_cha'] );
		if ( $cap === '' && $nm !== '' ) { self::relink_children( $ma_da, $nm, '(Phát sinh)' ); }   // xóa hạng mục lớn -> mục con thành phát sinh
		$wpdb->delete( $t, array( 'ma_da' => (string) $ma_da, 'row_no' => $row ) );
		return VHCP_Util::ok();
	}

	private static function relink_children( $ma_da, $old, $new ) {
		global $wpdb;
		$t = VHCP_DB::t( 'da_line' );
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET cap_cha=%s WHERE ma_da=%s AND TRIM(cap_cha)=%s", (string) $new, (string) $ma_da, (string) $old ) );
	}

	// ---------------------------------------------------------------- quy trình

	private static function set_status( $ma_da, $status ) {
		global $wpdb;
		$wpdb->update( VHCP_DB::t( 'da_index' ), array( 'trang_thai' => (string) $status ), array( 'ma_da' => (string) $ma_da ) );
	}

	public static function submit( $ma_da ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		if ( self::la_coso_chung( $ma_da ) ) { return VHCP_Util::err( 'Sổ Chi phí cơ sở CHUNG không cần duyệt' ); }
		if ( (string) ( $f['trang_thai'] !== '' ? $f['trang_thai'] : 'Đang làm' ) !== 'Đang làm' ) { return VHCP_Util::err( 'Chỉ gửi khi đang làm' ); }
		self::set_status( $ma_da, 'Chờ kế toán duyệt' );
		return VHCP_Util::ok();
	}

	public static function approve( $ma_da, $nguoi ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		if ( (string) $f['trang_thai'] !== 'Chờ kế toán duyệt' ) { return VHCP_Util::err( 'Dự án không ở trạng thái chờ duyệt' ); }
		self::set_status( $ma_da, 'Đã duyệt' );
		VHCP_Meta::set( 'daApp_' . $ma_da, VHCP_Util::now()->format( 'd/m/Y' ) );   // ngày chứng từ khi xuất MISA
		return VHCP_Util::ok();
	}

	public static function ret( $ma_da ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		if ( (string) $f['trang_thai'] !== 'Chờ kế toán duyệt' ) { return VHCP_Util::err( 'Chỉ trả khi đang chờ duyệt' ); }
		self::set_status( $ma_da, 'Đang làm' );
		return VHCP_Util::ok();
	}

	/**
	 * Đóng dự án. Dự án CHI TRỰC TIẾP — không có bước xin/duyệt tạm ứng như đơn tuần,
	 * nên đang làm là đóng được luôn, không đòi phải "Đã duyệt" trước.
	 */
	public static function close( $ma_da ) {
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		if ( self::la_coso_chung( $ma_da ) ) { return VHCP_Util::err( 'Sổ Chi phí cơ sở CHUNG không đóng — nó chạy xuyên suốt' ); }
		if ( (string) $f['trang_thai'] === 'Đã đóng' ) { return VHCP_Util::err( 'Dự án đã đóng' ); }
		self::set_status( $ma_da, 'Đã đóng' );
		return VHCP_Util::ok();
	}

	public static function reopen( $ma_da ) {
		if ( ! self::find( $ma_da ) ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
		self::set_status( $ma_da, 'Đang làm' );
		return VHCP_Util::ok();
	}

	public static function delete( $ma_da ) {
		global $wpdb;
		$f = self::find( $ma_da );
		if ( ! $f ) { return VHCP_Util::err( 'Không tìm thấy dự án' ); }
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
			$n_sc = count( VHCP_SoChi::theo_du_an( (string) $ma_da ) );
			if ( ! $n_sc ) { $n_sc = count( VHCP_SoChi::theo_du_an( (string) $f['ten'] ) ); }
			if ( $n_dong || $n_sc ) {
				return VHCP_Util::err( 'Sổ Chi phí cơ sở CHUNG còn ' . ( $n_dong + $n_sc )
					. ' dòng chi — không xoá được. Dòng ở đây có thể đã xuất MISA; xoá hết dòng trước rồi mới xoá sổ.' );
			}
		}
		if ( (string) $f['trang_thai'] === 'Đã đóng' ) { return VHCP_Util::err( 'Dự án đã đóng — Admin "Mở lại" trước khi xóa' ); }
		$wpdb->delete( VHCP_DB::t( 'da_line' ), array( 'ma_da' => (string) $ma_da ) );
		$wpdb->delete( VHCP_DB::t( 'da_index' ), array( 'ma_da' => (string) $ma_da ) );
		VHCP_Meta::del( 'daPay_' . $ma_da );
		VHCP_Meta::del( 'daApp_' . $ma_da );
		VHCP_Meta::del( 'daKy_' . $ma_da );
		/* XOÁ SỔ CHUNG THÌ GHIM LẠI VẾT TRỐNG.
		   ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG, ghi lại để lần sau khỏi đuổi theo: bỏ dòng này KHÔNG đổi kết
		      quả hôm nay — vết ghim cũ vẫn trỏ tới mã vừa xoá, mà `ma_coso_chung()` thấy
		      `find()` trả null nên đã trả '' rồi. Giữ vì nó CHỐT trạng thái "sổ này không có sổ
		      chung" thay vì để mỗi lượt gọi đi tra một mã đã chết; và ngày nào vết ghim bị dọn
		      (đổi khoá meta, khôi phục từ bản lưu) thì `ma_coso_chung()` ngã về "dự án loại
		      'Chi phí cơ sở' CŨ NHẤT" — tức một ĐƠN THEO TUẦN của nhân viên — và đơn ấy lặng lẽ
		      thành sổ chung: không đóng, không xoá, không đổi tên được, không báo gì. */
		if ( $la_chung ) { VHCP_Meta::set( self::MK_COSO_CHUNG, '-' ); }
		return VHCP_Util::ok();
	}

	/** Loại dự án -> loại chi phí tương ứng trong danh mục (tên trùng khớp, không phải đoán). */
	public static function loai_cp_mac_dinh( $loai_du_an ) {
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
		$t = VHCP_DB::t( 'da_line' );
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
				$giu    = VHCP_Cfg::ma_con_hop_le( $loai, $gian_x, $x['tk_no'] );
				$tk  = VHCP_Cfg::resolve_tk( $loai, $ht, array( 'tkNo' => $giu ), $gian_x );
				if ( $tk['tk_no'] === '' ) { $thieu[ $loai ] = 1; }
				if ( $loai === $cu && $tk['tk_no'] === trim( (string) $x['tk_no'] ) && $tk['tk_co'] === trim( (string) $x['tk_co'] ) ) { continue; }
				$wpdb->update( $t, array( 'loai_cp' => $loai, 'tk_no' => $tk['tk_no'], 'tk_co' => $tk['tk_co'], 'ma_dt' => $tk['ma_dt'] ), array( 'id' => (int) $x['id'] ) );
				$n++;
			}
		}
		return VHCP_Util::ok( array( 'updated' => $n, 'thieuMa' => array_keys( $thieu ), 'khongSuyDuoc' => $khong_suy ) );
	}

	/**
	 * Dùng chung cho báo cáo: mọi dự án kèm dòng hạng mục — ĐÚNG 2 LỆNH DB
	 * (1 lệnh danh mục + 1 lệnh toàn bộ dòng, rồi gom trong PHP).
	 * Trước đây mỗi dự án 1 lệnh nên 30 dự án là 31 lệnh.
	 */
	public static function all_with_lines() {
		$ti = VHCP_DB::t( 'da_index' );
		$tl = VHCP_DB::t( 'da_line' );
		$rows = VHCP_DB::rows( "SELECT * FROM $ti ORDER BY stt ASC" );
		$by   = array();
		foreach ( VHCP_DB::rows( "SELECT * FROM $tl ORDER BY ma_da ASC, row_no ASC" ) as $l ) {
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
		foreach ( VHCP_Meta::get_prefix( 'daPay_' ) as $k => $v ) {
			$o = json_decode( (string) $v, true );
			$out[ substr( $k, 7 ) ] = is_array( $o ) ? $o : array();
		}
		return $out;
	}

	/** Ngày kế toán duyệt của mọi dự án — 1 lệnh DB. */
	public static function approve_date_map() {
		$out = array();
		foreach ( VHCP_Meta::get_prefix( 'daApp_' ) as $k => $v ) {
			$out[ substr( $k, 7 ) ] = (string) $v;
		}
		return $out;
	}
}
