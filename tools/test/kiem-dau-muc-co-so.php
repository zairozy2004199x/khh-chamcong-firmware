<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐẦU MỤC (PHÂN LOẠI LỚN) KHAI ĐƯỢC, MỖI ĐẦU MỤC MANG "KHỐI CƠ SỞ".
 * Anh Thắng 24/09/2026: *"Chọn Phân Loại Lớn trước, Đến Phân Loại con (Nếu chọn chi phí cơ sở
 * thì sẽ có chọn thêm Cơ Sở) còn không thì nó là chi phí không có cơ sở"*.
 * 🔴 CHẠY THẬT: bảng rỗng → mặc định cũ; lưu {ten, coso} → đọc lại đúng; gói khởi động và gói
 *    Cấu hình cùng chở; chuẩn hoá ô khối; chối bảng rỗng; gói nhân bản có bảng này.
 * Chạy: php tools/test/kiem-dau-muc-co-so.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' ); }
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }

/* ═══ 1. Chưa khai → bốn tên mặc định, không đầu mục nào có khoá cơ sở (màn hiểu '*') ═══ */
VHCP_Cfg::write( VHCP_Cfg::DM, array() ); VHCP_Cfg::clear_cache();
teq( '⚠️ bảng rỗng → dau_muc_ds() = mặc định', VHCP_Cfg::DAU_MUC_MAC_DINH, VHCP_Cfg::dau_muc_ds() );
teq( '⚠️ bảng rỗng → dau_muc_coso() rỗng (mọi đầu mục = như cũ)', array(), VHCP_Cfg::dau_muc_coso() );

/* ═══ 2. 🔴 Lưu cây của anh Thắng ══════════════════════════════════════════════════════ */
$r = VHCP_Cfg::save_config( array( 'dauMucDs' => array(
	array( 'ten' => 'Chi Phí Cơ Sở KVC', 'coso' => 'kvc' ),
	array( 'ten' => 'Chi Phí Cơ Sở MTĐ', 'coso' => 'MTD' ),        // viết hoa, không dấu → chuẩn về 'mtd'
	array( 'ten' => 'Chi Phí Chung',     'coso' => '' ),           // không có cơ sở
	array( 'ten' => 'Khác',              'coso' => 'gì đó lạ' ),   // giá trị lạ → '*'
	array( 'ten' => 'chi phí chung',     'coso' => 'vp' ),         // trùng tên (hoa/thường) → bỏ
	array( 'ten' => '  ',                'coso' => 'vp' ),         // rỗng → bỏ
) ) );
t( '🔴 lưu được', ! empty( $r['success'] ), $r );
VHCP_Cfg::clear_cache();
teq( '🔴 dau_muc_ds() đúng thứ tự, khử trùng', array( 'Chi Phí Cơ Sở KVC', 'Chi Phí Cơ Sở MTĐ', 'Chi Phí Chung', 'Khác' ), VHCP_Cfg::dau_muc_ds() );
teq( '🔴 dau_muc_coso(): KVC→kvc · MTĐ→mtd (chuẩn hoá) · Chung→"" (không cơ sở) · lạ→"*"',
	array( 'Chi Phí Cơ Sở KVC' => 'kvc', 'Chi Phí Cơ Sở MTĐ' => 'mtd', 'Chi Phí Chung' => '', 'Khác' => '*' ), VHCP_Cfg::dau_muc_coso() );
teq( '   khoi_coso_chuan: mb/mn (miền) cũng là khối hợp lệ', 'mn', VHCP_Cfg::khoi_coso_chuan( 'MN' ) );
teq( '   khoi_coso_chuan: rỗng giữ rỗng, * giữ *', array( '', '*' ), array( VHCP_Cfg::khoi_coso_chuan( ' ' ), VHCP_Cfg::khoi_coso_chuan( '*' ) ) );

/* ═══ 3. Hai gói cùng chở ═════════════════════════════════════════════════════════════ */
$cfg = VHCP_Cfg::get_config();
teq( '🔴 gói Cấu hình chở dauMucDs', array( 'Chi Phí Cơ Sở KVC', 'Chi Phí Cơ Sở MTĐ', 'Chi Phí Chung', 'Khác' ), $cfg['dauMucDs'] );
teq( '🔴 gói Cấu hình chở dauMucCoSo', 'kvc', $cfg['dauMucCoSo']['Chi Phí Cơ Sở KVC'] );
$b = VHCP_Don::get_bootstrap();
$b = isset( $b['dauMucCoSo'] ) ? $b : ( isset( $b['data'] ) ? $b['data'] : $b );
t( '🔴 gói khởi động chở dauMucCoSo (form ẩn/hiện ô Cơ sở theo đây)', isset( $b['dauMucCoSo'] ) && '' === $b['dauMucCoSo']['Chi Phí Chung'] && 'mtd' === $b['dauMucCoSo']['Chi Phí Cơ Sở MTĐ'], isset( $b['dauMucCoSo'] ) ? $b['dauMucCoSo'] : array_keys( $b ) );
teq( '   gói khởi động dauMucDs cùng danh sách', VHCP_Cfg::dau_muc_ds(), $b['dauMucDs'] );

/* ═══ 4. Mảng CHUỖI (đường cũ) → giữ khối cơ sở đang lưu, tên mới → '*' ═══════════════ */
$r = VHCP_Cfg::save_config( array( 'dauMucDs' => array( 'Chi Phí Chung', 'Chi Phí Cơ Sở KVC', 'Mới toanh' ) ) );
t( '   lưu mảng chuỗi được', ! empty( $r['success'] ), $r );
VHCP_Cfg::clear_cache();
teq( '🔴 gửi tên thôi → GIỮ khối cơ sở đã lưu, tên mới = "*"', array( 'Chi Phí Chung' => '', 'Chi Phí Cơ Sở KVC' => 'kvc', 'Mới toanh' => '*' ), VHCP_Cfg::dau_muc_coso() );

/* ═══ 4b. 🔴 Đổi tên đầu mục → loại đang gắn tên cũ ĐI THEO (anh Thắng: "Muốn sửa phân loại chi phí này") ═ */
VHCP_Cfg::save_config( array( 'loaiChiPhi' => array(
	array( 'ten' => 'Chi phí thuê mall', 'tkNo' => '6421', 'khoi' => 'mn', 'dauMuc' => 'Chi phí tiền thuê' ),
	array( 'ten' => 'Chi phí điện nước', 'tkNo' => '6427', 'khoi' => 'mn', 'dauMuc' => 'chi phí tiền thuê' ),   // khác hoa/thường vẫn theo
	array( 'ten' => 'Chi phí cơ sở',     'tkNo' => '64196', 'khoi' => 'mn', 'dauMuc' => 'Chi Phí Cơ Sở KVC' ),
) ) ); VHCP_Cfg::clear_cache();
/* Đầu mục sắp đổi tên đang mang khối cơ sở 'vp' — đổi tên không gửi coso thì phải GIỮ 'vp'. */
VHCP_Cfg::save_config( array( 'dauMucDs' => array( array( 'ten' => 'Chi phí tiền thuê', 'coso' => 'vp' ), array( 'ten' => 'Chi Phí Cơ Sở KVC', 'coso' => 'kvc' ) ) ) ); VHCP_Cfg::clear_cache();
$r = VHCP_Cfg::save_config( array( 'dauMucDs' => array(
	array( 'ten' => 'Chi phí mặt bằng', 'goc' => 'Chi phí tiền thuê' ),            // đổi tên, không gửi coso → giữ theo tên cũ
	array( 'ten' => 'Chi Phí Cơ Sở KVC', 'coso' => 'kvc', 'goc' => 'Chi Phí Cơ Sở KVC' ),
	array( 'ten' => 'Chi Phí Chung', 'coso' => '', 'goc' => '' ),                   // dòng mới
) ) );
t( '   lưu đổi tên được', ! empty( $r['success'] ), $r ); VHCP_Cfg::clear_cache();
$dm = array(); foreach ( VHCP_Cfg::get_config()['loaiChiPhi'] as $x ) { $dm[ $x['ten'] ] = $x['dauMuc']; }
teq( '🔴 loại gắn tên cũ → sang tên mới', 'Chi phí mặt bằng', $dm['Chi phí thuê mall'] );
teq( '🔴 khác hoa/thường vẫn theo', 'Chi phí mặt bằng', $dm['Chi phí điện nước'] );
teq( '   loại của đầu mục khác không đụng', 'Chi Phí Cơ Sở KVC', $dm['Chi phí cơ sở'] );
teq( '   danh sách đầu mục là tên mới', array( 'Chi phí mặt bằng', 'Chi Phí Cơ Sở KVC', 'Chi Phí Chung' ), VHCP_Cfg::dau_muc_ds() );
teq( '🔴 đổi tên mà không gửi coso → giữ khối cơ sở của tên cũ ("vp")', 'vp', VHCP_Cfg::dau_muc_coso()['Chi phí mặt bằng'] );
/* Không gửi goc (màn cũ) mà tên khác → KHÔNG đoán, loại giữ nguyên. */
VHCP_Cfg::save_config( array( 'dauMucDs' => array( array( 'ten' => 'Tên khác hẳn', 'coso' => '*' ), array( 'ten' => 'Chi Phí Cơ Sở KVC', 'coso' => 'kvc' ) ) ) ); VHCP_Cfg::clear_cache();
$dm = array(); foreach ( VHCP_Cfg::get_config()['loaiChiPhi'] as $x ) { $dm[ $x['ten'] ] = $x['dauMuc']; }
teq( '   không gửi goc → không đổi loại nào (không đoán)', 'Chi phí mặt bằng', $dm['Chi phí thuê mall'] );
teq( '   doi_ten_dau_muc_o_loai trả số dòng đổi', 2, VHCP_Cfg::doi_ten_dau_muc_o_loai( array( 'Chi phí mặt bằng' => 'Chi phí tiền thuê' ) ) );
/* Trả bảng về ba dòng cho hai mục dưới. */
VHCP_Cfg::save_config( array( 'dauMucDs' => array( 'Chi Phí Chung', 'Chi Phí Cơ Sở KVC', 'Mới toanh' ) ) ); VHCP_Cfg::clear_cache();

/* ═══ 5. Chối bảng rỗng ═══════════════════════════════════════════════════════════════ */
$r = VHCP_Cfg::save_config( array( 'dauMucDs' => array( '', '  ' ) ) );
t( '🔴 xoá hết → CHỐI, nói rõ hệ dùng lại mặc định', empty( $r['success'] ) && false !== mb_strpos( (string) $r['error'], 'mặc định' ), $r );
teq( '   và bảng cũ còn nguyên', 3, count( VHCP_Cfg::dau_muc_rows() ) );

/* ═══ 6. Gói nhân bản chở bảng này ═════════════════════════════════════════════════════ */
t( '🔴 gói nhân bản cấu hình có CH_DauMuc trong danh sách trắng', isset( VHCP_Cfg::goi_bang_ds()[ VHCP_Cfg::DM ] ) );
$g = VHCP_Cfg::xuat_goi_cau_hinh( array( VHCP_Cfg::DM ) );
teq( '   xuất ra đúng 3 dòng {tên, khối}', 3, count( $g['bang'][ VHCP_Cfg::DM ] ) );

if ( $TRUOT ) { echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n"; foreach ( $TRUOT as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $DAT phép: đầu mục khai được kèm khối cơ sở, xuống cả hai gói, chuẩn hoá và chối bảng rỗng.\n";
