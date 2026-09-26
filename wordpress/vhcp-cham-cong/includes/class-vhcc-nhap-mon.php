<?php
/**
 * NHẬP MÔN NHÂN VIÊN MỚI — bảng "Bắt đầu cùng K&H" + NỘI QUY CÔNG TY + hướng dẫn nhanh.
 *
 * Anh Thắng 26/09/2026: *"Với nhân viên mới, tài khoản lần đầu kích hoạt sẽ hiện phía dưới về
 * Nội Quy Công Ty, và 1 số hướng dẫn khác"* → xem mẫu (artifact "Nhập môn nhân viên mới") →
 * *"làm theo mẫu này luôn đi em"*.
 *
 * =================================================================================================
 * BA CUỐN SỔ (bảng `cai_dat`), KHÔNG TỰ XOÁ
 * =================================================================================================
 *   · `O_NQ`   — NỘI QUY: phiên bản, ngày áp dụng, các mục (tiêu đề + dòng). Công ty soạn ở màn
 *                Tiếp nhận nhân sự. Đổi nội dung là lên phiên bản mới → mọi người đồng ý lại.
 *   · `O_DY`   — AI ĐỒNG Ý NỘI QUY BẢN NÀO, LÚC NÀO (kèm IP, thiết bị) — như ký nhận nội quy.
 *   · `O_TICH` — việc nhân viên tự tích (cài app) và cờ "ẩn bảng".
 *
 * 🔴 SÁU VIỆC, PHẦN LỚN MÁY TỰ BIẾT: kích hoạt (đã đăng nhập) · nội quy (đã đồng ý bản hiện
 *    hành) · đổi PIN (khác PIN hệ thống cấp lúc tiếp nhận) · cài app (tự tích) · chấm lượt đầu
 *    (có lượt chấm vào) · ký hợp đồng (đã ký điện tử bộ hồ sơ). Việc không áp dụng (người không
 *    đi qua Tiếp nhận thì không có PIN cấp sẵn, không có hợp đồng điện tử) thì không bày ra.
 * 🔴 AI THẤY BẢNG: người vào làm chưa quá `NGAY_MOI` ngày và chưa ẩn bảng. Ngoài ra, NỘI QUY
 *    ĐỔI BẢN MỚI thì MỌI người (kể cả người cũ) thấy một dòng nhắc đọc & đồng ý lại.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_NhapMon {

	const O_NQ   = 'NOI_QUY';
	const O_DY   = 'NOI_QUY_DONG_Y';
	const O_TICH = 'NHAP_MON_TICH';
	/** 🧪 Mã NV đang bật CHẾ ĐỘ THỬ: thấy bảng nhập môn như người mới, dù vào làm đã lâu. */
	const O_THU  = 'NHAP_MON_THU';

	/** Vào làm trong bấy nhiêu ngày thì còn là "người mới". */
	const NGAY_MOI = 30;

	/** Soạn nội quy = việc nhân sự → bậc Kế toán (cùng cửa màn Tiếp nhận). */
	const QUYEN = 'ho_so';

	/**
	 * NỘI QUY SOẠN SẴN CHO KHU VUI CHƠI — anh Thắng 26/09/2026: *"Soạn giúp anh nội quy thật cho khu
	 * vui chơi"*. Theo BLLĐ 2019 (Điều 118: đủ nội dung bắt buộc) + NĐ 145/2020 (Điều 69). Công ty
	 * đọc lại, sửa cho đúng thực tế (giờ mở cửa, quy trình riêng) rồi bấm Lưu ở màn Tiếp nhận.
	 */
	public static function mac_dinh() {
		return array(
			'ban' => '1.0', 'apDung' => '', 'luc' => '', 'boi' => '',
			'muc' => array(
				array( 'tieu' => 'Phạm vi & nguyên tắc chung', 'dong' => array(
					'Nội quy này áp dụng cho mọi người lao động làm việc tại các khu vui chơi và văn phòng của Công ty, kể cả người đang thử việc, học việc, làm bán thời gian.',
					'Nội quy được xây dựng theo Bộ luật Lao động 2019 (Điều 118) và Nghị định 145/2020/NĐ-CP; điều gì nội quy chưa nói thì làm theo hợp đồng lao động và pháp luật.',
					'Người lao động có trách nhiệm đọc, hiểu và thực hiện đúng nội quy; bấm "Đồng ý nội quy" trên ứng dụng có giá trị như ký nhận.',
					'Khu vui chơi phục vụ trẻ em: an toàn của trẻ luôn được đặt trên doanh thu và trên tốc độ phục vụ.',
				) ),
				array( 'tieu' => 'Giờ làm việc, nghỉ ngơi & chấm công', 'dong' => array(
					'Làm việc theo ca do cửa hàng trưởng xếp trên lịch làm việc; giờ làm bình thường không quá 8 giờ/ngày và 48 giờ/tuần.',
					'Làm thêm giờ chỉ khi được quản lý yêu cầu hoặc đồng ý, người lao động tự nguyện; tổng giờ làm thêm không quá 40 giờ/tháng và 200 giờ/năm, được trả lương làm thêm theo luật.',
					'Làm từ 6 giờ/ngày trở lên được nghỉ giữa giờ ít nhất 30 phút liên tục (ca đêm ít nhất 45 phút); giữa hai ca nghỉ ít nhất 12 giờ; mỗi tuần nghỉ ít nhất 24 giờ liên tục.',
					'Có mặt trước giờ ca ít nhất 5 phút để thay đồng phục, nhận bàn giao. Chấm vào khi bắt đầu ca, chấm ra khi kết thúc ca, bằng ứng dụng tại đúng cơ sở mình làm (có chụp ảnh khuôn mặt).',
					'Nghiêm cấm chấm công hộ, nhờ người khác chấm hộ, chấm khi không có mặt tại cơ sở.',
					'Quên chấm: gửi "Xin bù giờ" trên ứng dụng ngay trong ngày. Đi trễ: gửi "Đơn đi trễ" trước giờ ca. Không tự ý rời vị trí hoặc về sớm khi chưa được quản lý đồng ý và chưa bàn giao.',
				) ),
				array( 'tieu' => 'Nghỉ phép, nghỉ lễ & đơn từ', 'dong' => array(
					'Nghỉ lễ, Tết hưởng nguyên lương theo Điều 112 Bộ luật Lao động; ai phải làm vào ngày lễ được trả lương theo luật.',
					'Đủ 12 tháng làm việc được nghỉ phép năm 12 ngày hưởng nguyên lương; cứ đủ 5 năm làm việc được thêm 1 ngày. Làm chưa đủ 12 tháng thì tính theo tỷ lệ số tháng làm việc.',
					'Nghỉ việc riêng hưởng nguyên lương: bản thân kết hôn 3 ngày; con kết hôn 1 ngày; cha mẹ (hai bên), vợ hoặc chồng, con chết 3 ngày.',
					'Xin nghỉ gửi đơn trên ứng dụng trước ít nhất 1 ngày (nghỉ từ 3 ngày trở lên: trước 7 ngày) và chờ quản lý duyệt. Ốm đau, việc gấp: báo ngay cho cửa hàng trưởng rồi bổ sung đơn.',
					'Đổi ca với đồng nghiệp: gửi yêu cầu đổi lịch trên ứng dụng, chỉ có hiệu lực khi quản lý duyệt.',
				) ),
				array( 'tieu' => 'Đồng phục, tác phong & phục vụ khách', 'dong' => array(
					'Mặc đồng phục sạch sẽ, đeo bảng tên, đi giày hoặc vớ theo quy định của khu; tóc gọn gàng, móng tay ngắn, không đeo trang sức sắc nhọn khi hướng dẫn trẻ chơi.',
					'Chào khách, niềm nở, nói năng lịch sự; tuyệt đối không quát mắng, doạ nạt, xô kéo trẻ em.',
					'Không dùng điện thoại vào việc riêng khi đang trực khu chơi hoặc phục vụ khách; không ăn uống, nằm ngồi trên thiết bị chơi.',
					'Không hút thuốc (kể cả thuốc lá điện tử), không uống rượu bia, không làm việc khi đã uống rượu bia hoặc dùng chất kích thích.',
					'Khách góp ý, khiếu nại: lắng nghe, xin lỗi vì sự bất tiện và báo cửa hàng trưởng xử lý; không tranh cãi với khách.',
				) ),
				array( 'tieu' => 'An toàn trẻ em & khu vui chơi', 'dong' => array(
					'Đầu ca kiểm tra toàn bộ thiết bị theo danh sách kiểm tra: lưới, đệm, dây, ốc vít, cạnh sắc, nhà bóng, cầu trượt, nguồn điện. Chưa kiểm tra xong thì chưa mở khu.',
					'Phát hiện thiết bị hư hỏng hoặc không an toàn: dừng ngay, rào chắn, treo biển tạm ngưng và báo quản lý; không tự ý sửa khi không được giao.',
					'Luôn có người trực quan sát khu chơi; không để khu vực nào vắng người giám sát. Hướng dẫn trẻ chơi đúng độ tuổi, chiều cao, số người cho phép của từng trò.',
					'Chỉ giao trẻ ra khỏi khu cho đúng người lớn đi cùng (đối chiếu vé, vòng tay hoặc thẻ). Trẻ lạc: báo ngay cho cửa hàng trưởng, phát thông báo, canh các lối ra, không để trẻ đi với người lạ.',
					'Trẻ bị ngã, chấn thương: sơ cứu theo hướng dẫn, báo phụ huynh và quản lý, gọi cấp cứu 115 khi cần; ghi biên bản sự cố ngay trong ca.',
					'Biết vị trí tủ sơ cứu, bình chữa cháy, lối thoát hiểm và giữ lối thoát hiểm luôn thông thoáng.',
				) ),
				array( 'tieu' => 'Vệ sinh, an toàn lao động & phòng cháy chữa cháy', 'dong' => array(
					'Vệ sinh, khử khuẩn thiết bị, bóng, đồ chơi theo lịch; khu chơi, nhà vệ sinh, khu ăn uống luôn sạch, khô ráo.',
					'Sử dụng đúng trang thiết bị bảo hộ được cấp; báo ngay khi thấy nguy cơ mất an toàn cho khách hoặc cho chính mình.',
					'Tham gia đầy đủ các buổi huấn luyện an toàn, sơ cứu, phòng cháy chữa cháy do Công ty tổ chức.',
					'Không tự ý câu mắc điện, dùng bếp, nến hoặc lửa trần trong khu; cuối ngày tắt thiết bị điện theo quy trình đóng cửa.',
					'Khi có cháy hoặc sự cố khẩn cấp: ưu tiên sơ tán trẻ em và khách theo lối thoát hiểm, gọi 114, báo quản lý.',
				) ),
				array( 'tieu' => 'Tiền quỹ, vé & tài sản công ty', 'dong' => array(
					'Thu tiền, bán vé, nạp thẻ đúng bảng giá và đúng chương trình khuyến mãi đang áp dụng; mọi giao dịch phải qua máy tính tiền hoặc phần mềm của Công ty.',
					'Kiểm đếm quỹ đầu ca và cuối ca có người chứng kiến; chênh lệch ghi biên bản và báo quản lý ngay trong ca.',
					'Không tự ý cho chơi miễn phí, giảm giá, cho nợ; không nhận tiền riêng, tiền chuyển khoản vào tài khoản cá nhân từ khách.',
					'Giữ gìn thiết bị, đồ chơi, dụng cụ được giao; không mang tài sản của Công ty ra ngoài khi chưa được phép. Đồ khách để quên: ghi sổ và nộp cho quản lý.',
				) ),
				array( 'tieu' => 'Bảo mật thông tin & hình ảnh', 'dong' => array(
					'Giữ kín PIN, mật khẩu; không cho người khác dùng tài khoản của mình.',
					'Không tiết lộ ra ngoài doanh thu, giá vốn, danh sách khách hàng, số điện thoại khách, hợp đồng và các tài liệu nội bộ của Công ty.',
					'Không chụp ảnh, quay phim trẻ em rồi đăng lên mạng xã hội cá nhân khi chưa có sự đồng ý của phụ huynh và của Công ty (Luật Trẻ em 2016 bảo vệ bí mật đời sống riêng tư của trẻ).',
					'Không phát ngôn với báo chí, không đăng thông tin nội bộ, sự cố của khu lên mạng khi chưa được Giám đốc cho phép.',
				) ),
				array( 'tieu' => 'Phòng, chống quấy rối tình dục tại nơi làm việc', 'dong' => array(
					'Nghiêm cấm mọi hành vi quấy rối tình dục bằng lời nói, cử chỉ, hình ảnh, tin nhắn hoặc đụng chạm, với đồng nghiệp, khách hàng và đặc biệt là trẻ em.',
					'Chỉ tiếp xúc cơ thể với trẻ khi cần để bảo đảm an toàn hoặc hỗ trợ chơi, trong tầm nhìn của người khác; không đưa trẻ vào nơi khuất.',
					'Người bị quấy rối hoặc chứng kiến hành vi quấy rối báo cho cửa hàng trưởng hoặc trực tiếp Giám đốc; Công ty giữ kín danh tính người báo và xử lý trong thời hạn quy định.',
					'Quấy rối tình dục tại nơi làm việc là hành vi có thể bị xử lý kỷ luật sa thải.',
				) ),
				array( 'tieu' => 'Tạm thời chuyển làm công việc khác', 'dong' => array(
					'Khi gặp khó khăn đột xuất (thiên tai, dịch bệnh, sự cố, nhu cầu kinh doanh), Công ty có thể tạm thời điều người lao động sang cơ sở hoặc công việc khác phù hợp sức khoẻ, giới tính.',
					'Công ty báo trước ít nhất 3 ngày làm việc, nói rõ thời hạn; tổng thời gian tạm chuyển không quá 60 ngày làm việc cộng dồn trong 1 năm, quá thời hạn này phải được người lao động đồng ý bằng văn bản.',
					'Tiền lương công việc mới không thấp hơn 85% lương công việc cũ và không thấp hơn lương tối thiểu vùng; được giữ nguyên lương cũ trong 30 ngày làm việc đầu.',
				) ),
				array( 'tieu' => 'Hành vi vi phạm & hình thức kỷ luật', 'dong' => array(
					'Hình thức kỷ luật (Điều 124): khiển trách; kéo dài thời hạn nâng lương không quá 6 tháng; cách chức; sa thải.',
					'Khiển trách: đi trễ, về sớm, quên chấm công không lý do từ 3 lần trong tháng; dùng điện thoại việc riêng khi trực khu; sai đồng phục, tác phong; bỏ vị trí khi chưa bàn giao.',
					'Kéo dài thời hạn nâng lương: tái phạm khi đang bị khiển trách; không kiểm tra thiết bị đầu ca; để khu chơi vắng người giám sát; tự ý cho chơi miễn phí, giảm giá; chấm công hộ hoặc nhờ chấm hộ.',
					'Cách chức (người giữ chức vụ quản lý): tái phạm khi đang bị kéo dài thời hạn nâng lương; để xảy ra sự cố an toàn nghiêm trọng do không tổ chức kiểm tra, giám sát.',
					'Sa thải (Điều 125): trộm cắp, tham ô, đánh bạc, cố ý gây thương tích (kể cả đánh, bạo hành trẻ em), sử dụng ma tuý tại nơi làm việc; tiết lộ bí mật kinh doanh; quấy rối tình dục tại nơi làm việc; tái phạm khi chưa được xoá kỷ luật kéo dài thời hạn nâng lương hoặc cách chức; tự ý bỏ việc 5 ngày cộng dồn trong 30 ngày hoặc 20 ngày cộng dồn trong 365 ngày không có lý do chính đáng.',
					'Công ty không phạt tiền, không cắt lương thay cho việc xử lý kỷ luật, không xử lý kỷ luật hành vi không có trong nội quy (Điều 127).',
				) ),
				array( 'tieu' => 'Trách nhiệm vật chất (bồi thường thiệt hại)', 'dong' => array(
					'Làm hư hỏng, mất dụng cụ, thiết bị, tài sản hoặc gây thiệt hại khác cho Công ty thì phải bồi thường theo Điều 129 Bộ luật Lao động.',
					'Thiệt hại do sơ suất, giá trị không quá 10 tháng lương tối thiểu vùng: bồi thường nhiều nhất 3 tháng tiền lương, trừ dần vào lương hằng tháng không quá 30% tiền lương thực nhận.',
					'Thiệt hại do cố ý hoặc vượt mức trên, làm mất tiền quỹ, hàng hoá được giao quản lý: bồi thường theo giá trị thực tế, có xem xét lỗi, hoàn cảnh và mức độ thiệt hại.',
					'Thiệt hại do thiên tai, hoả hoạn, sự kiện bất khả kháng mà đã làm đúng quy trình thì không phải bồi thường.',
				) ),
				array( 'tieu' => 'Thẩm quyền & trình tự xử lý kỷ luật', 'dong' => array(
					'Người có thẩm quyền xử lý kỷ luật: Giám đốc Công ty hoặc người được Giám đốc uỷ quyền bằng văn bản.',
					'Mọi vụ việc được lập biên bản; Công ty phải chứng minh lỗi, người lao động được trình bày, được nhờ người khác bào chữa; có sự tham gia của tổ chức đại diện người lao động nếu có.',
					'Thời hiệu xử lý kỷ luật là 6 tháng kể từ ngày xảy ra vi phạm; 12 tháng đối với vi phạm liên quan tài chính, tài sản, tiết lộ bí mật kinh doanh.',
					'Quyết định kỷ luật được lập thành văn bản và gửi cho người lao động; người lao động có quyền khiếu nại theo quy định của pháp luật.',
				) ),
			),
		);
	}

	public static function noi_quy() {
		$d = VHCC_Luong::cai_dat( self::O_NQ, null );
		return is_array( $d ) && ! empty( $d['muc'] ) ? array_merge( self::mac_dinh(), $d ) : self::mac_dinh();
	}

	/** Thời gian đọc ước lượng (~900 ký tự một phút, tối thiểu 2 phút). */
	public static function phut_doc( $nq ) {
		$n = 0;
		foreach ( (array) $nq['muc'] as $m ) { foreach ( (array) $m['dong'] as $d ) { $n += function_exists( 'mb_strlen' ) ? mb_strlen( $d, 'UTF-8' ) : strlen( $d ); } }
		return max( 2, (int) ceil( $n / 900 ) );
	}

	/** Nội quy ra chữ để sửa: "## Tiêu đề" rồi mỗi dòng "- nội dung". */
	public static function ra_chu( $nq = null ) {
		$nq = null === $nq ? self::noi_quy() : $nq;
		$o = array();
		foreach ( (array) $nq['muc'] as $m ) {
			$o[] = '## ' . $m['tieu'];
			foreach ( (array) $m['dong'] as $d ) { $o[] = '- ' . $d; }
			$o[] = '';
		}
		return trim( implode( "\n", $o ) );
	}

	/** Đọc chữ ngược lại thành các mục. */
	public static function doc_chu( $chu ) {
		$muc = array(); $cur = null;
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $chu ) as $l ) {
			$l = trim( $l );
			if ( '' === $l ) { continue; }
			if ( 0 === strpos( $l, '##' ) ) {
				if ( $cur ) { $muc[] = $cur; }
				$cur = array( 'tieu' => trim( ltrim( $l, '#' ) ), 'dong' => array() );
				continue;
			}
			if ( ! $cur ) { $cur = array( 'tieu' => 'Quy định chung', 'dong' => array() ); }
			$cur['dong'][] = trim( preg_replace( '/^[-*•]\s*/u', '', $l ) );
		}
		if ( $cur ) { $muc[] = $cur; }
		return array_values( array_filter( $muc, function ( $m ) { return '' !== $m['tieu'] && $m['dong']; } ) );
	}

	/**
	 * Lưu nội quy. Nội dung đổi mà đã có người đồng ý bản đang có thì LÊN BẢN (1.0 → 1.1) — mọi
	 * người phải đồng ý lại — trừ khi `giu_ban` (sửa chính tả, không đổi ý).
	 */
	public static function dat_noi_quy( $u, $chu, $ap_dung = '', $giu_ban = false ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Soạn nội quy' ) );
		}
		$muc = self::doc_chu( $chu );
		if ( ! $muc ) { return array( 'ok' => false, 'error' => 'Nội quy trống — mỗi mục bắt đầu bằng "## Tiêu đề", mỗi dòng bắt đầu bằng "- ".' ); }
		$cu = self::noi_quy();
		$doi = wp_json_encode( $muc ) !== wp_json_encode( $cu['muc'] );
		$ban = (string) $cu['ban'];
		/* Chưa ai đồng ý bản đang có thì không cần lên bản — sửa thẳng (VD thay bản nháp minh hoạ). */
		$co_nguoi = false;
		foreach ( self::so( self::O_DY ) as $ds ) {
			foreach ( (array) $ds as $x ) { if ( (string) $x['ban'] === $ban ) { $co_nguoi = true; break 2; } }
		}
		if ( $doi && ! $giu_ban && $co_nguoi ) {
			$p = explode( '.', $ban );
			$ban = (int) $p[0] . '.' . ( ( isset( $p[1] ) ? (int) $p[1] : 0 ) + 1 );
		}
		$nq = array( 'ban' => $ban, 'apDung' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $ap_dung ) ? (string) $ap_dung : (string) $cu['apDung'],
			'muc' => $muc, 'luc' => current_time( 'mysql' ), 'boi' => isset( $u['name'] ) ? (string) $u['name'] : '' );
		VHCC_Luong::dat_cai_dat( self::O_NQ, $nq, $u );
		return array( 'ok' => true, 'ban' => $ban, 'lenBan' => $ban !== (string) $cu['ban'] );
	}

	/* ============================================================== đồng ý */

	private static function so( $o ) {
		$d = VHCC_Luong::cai_dat( $o, null );
		return is_array( $d ) ? $d : array();
	}

	/** Lần đồng ý gần nhất của một người (hoặc null). */
	public static function dong_y_cua( $ma ) {
		$so = self::so( self::O_DY );
		$k = strtolower( trim( (string) $ma ) );
		if ( empty( $so[ $k ] ) ) { return null; }
		$ds = (array) $so[ $k ];
		return end( $ds );
	}

	public static function da_dong_y_ban( $ma, $ban ) {
		$so = self::so( self::O_DY );
		$k = strtolower( trim( (string) $ma ) );
		foreach ( (array) ( isset( $so[ $k ] ) ? $so[ $k ] : array() ) as $x ) {
			if ( (string) $x['ban'] === (string) $ban ) { return $x; }
		}
		return null;
	}

	/** Nhân viên bấm "Đồng ý nội quy". `$ban` phải đúng bản đang hiệu lực (không đồng ý bản cũ). */
	public static function dong_y( $u, $ban, $ip = '', $ua = '' ) {
		$ma = isset( $u['ma_nv'] ) ? trim( (string) $u['ma_nv'] ) : '';
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Tài khoản chưa gắn mã nhân viên.' ); }
		$nq = self::noi_quy();
		if ( (string) $ban !== (string) $nq['ban'] ) {
			return array( 'ok' => false, 'error' => 'Nội quy vừa được cập nhật lên bản ' . $nq['ban'] . ' — tải lại để đọc bản mới.' );
		}
		if ( $x = self::da_dong_y_ban( $ma, $nq['ban'] ) ) { return array( 'ok' => true, 'luc' => $x['luc'], 'ban' => $nq['ban'] ); }
		$so = self::so( self::O_DY );
		$k = strtolower( $ma );
		$so[ $k ][] = array( 'ban' => (string) $nq['ban'], 'luc' => current_time( 'mysql' ),
			'ten' => isset( $u['name'] ) ? (string) $u['name'] : '', 'ip' => substr( (string) $ip, 0, 64 ), 'ua' => substr( (string) $ua, 0, 200 ) );
		VHCC_Luong::dat_cai_dat( self::O_DY, $so, array( 'name' => 'NV đồng ý nội quy: ' . $ma ) );
		return array( 'ok' => true, 'luc' => end( $so[ $k ] )['luc'], 'ban' => $nq['ban'] );
	}

	/** Nhân viên tự tích (chỉ "cai" = đã cài app) hoặc ẩn bảng ("an"). */
	public static function tich( $u, $k, $co = true ) {
		$ma = isset( $u['ma_nv'] ) ? strtolower( trim( (string) $u['ma_nv'] ) ) : '';
		if ( '' === $ma || ! in_array( $k, array( 'cai', 'an' ), true ) ) { return array( 'ok' => false, 'error' => 'Không tích được việc này.' ); }
		$so = self::so( self::O_TICH );
		if ( $co ) { $so[ $ma ][ $k ] = current_time( 'mysql' ); } else { unset( $so[ $ma ][ $k ] ); }
		VHCC_Luong::dat_cai_dat( self::O_TICH, $so, array( 'name' => 'NV nhập môn: ' . $ma ) );
		return array( 'ok' => true );
	}

	/* ============================================================== 🧪 chế độ thử */

	/** Anh Thắng 26/09/2026: *"Bật tính năng thử các chức năng mới vừa làm để test"*. */
	public static function ds_thu() {
		return array_values( array_filter( array_map( 'strval', self::so( self::O_THU ) ) ) );
	}

	/** Đặt danh sách mã thử (cách nhau bằng dấu phẩy / khoảng trắng / xuống dòng). Chỉ nhận mã có hồ sơ. */
	public static function dat_thu( $u, $chu ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Chế độ thử nhập môn' ) );
		}
		$ds = array(); $sai = array();
		foreach ( preg_split( '/[\s,;]+/', (string) $chu, -1, PREG_SPLIT_NO_EMPTY ) as $ma ) {
			if ( VHCC_NhanSu::ho_so( $ma ) || VHCC_NhanSu::ho_so( strtoupper( $ma ) ) ) { $ds[ strtolower( $ma ) ] = true; } else { $sai[] = $ma; }
		}
		if ( $sai ) { return array( 'ok' => false, 'error' => 'Không có hồ sơ mã: ' . implode( ', ', $sai ) . '.' ); }
		VHCC_Luong::dat_cai_dat( self::O_THU, array_keys( $ds ), $u );
		return array( 'ok' => true, 'so' => count( $ds ) );
	}

	/**
	 * Làm lại từ đầu cho MỘT mã đang thử: xoá việc tự tích + các lần đồng ý nội quy của mã ấy.
	 * ⚠️ Chỉ mã trong danh sách thử — lần đồng ý của nhân viên thật là bằng chứng, không cho xoá.
	 */
	public static function lam_lai( $u, $ma ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Chế độ thử nhập môn' ) );
		}
		$k = strtolower( trim( (string) $ma ) );
		if ( '' === $k || ! in_array( $k, self::ds_thu(), true ) ) {
			return array( 'ok' => false, 'error' => 'Chỉ làm lại được cho mã đang bật chế độ thử.' );
		}
		foreach ( array( self::O_TICH, self::O_DY ) as $o ) {
			$so = self::so( $o );
			if ( isset( $so[ $k ] ) ) { unset( $so[ $k ] ); VHCC_Luong::dat_cai_dat( $o, $so, $u ); }
		}
		return array( 'ok' => true );
	}

	/* ============================================================== trạng thái cho trạm */

	/**
	 * Mọi thứ trạm cần để vẽ bảng nhập môn + màn nội quy.
	 * @return array { hien, moi, xong, tong, viec:[{k,ten,mo,xong,tuTich}], noiQuy:{ban,apDung,muc,dongY}, nhacLai }
	 */
	public static function trang_thai( $u ) {
		global $wpdb;
		$ma = isset( $u['ma_nv'] ) ? trim( (string) $u['ma_nv'] ) : '';
		$nq = self::noi_quy();
		$dy = '' !== $ma ? self::da_dong_y_ban( $ma, $nq['ban'] ) : null;
		$ra = array( 'hien' => false, 'moi' => false, 'thu' => false, 'xong' => 0, 'tong' => 0, 'viec' => array(),
			'noiQuy' => array( 'ban' => $nq['ban'], 'apDung' => $nq['apDung'], 'muc' => $nq['muc'], 'dongY' => $dy ? $dy['luc'] : '',
				'cty' => class_exists( 'VHCC_Pdf' ) ? VHCC_Pdf::ten_cong_ty() : '' ),
			'nhacLai' => false );
		if ( '' === $ma ) { return $ra; }
		$hs = VHCC_NhanSu::ho_so( $ma );
		$tn = class_exists( 'VHCC_TiepNhan' ) ? VHCC_TiepNhan::ban_ghi( $ma ) : null;
		$vao = $hs && ! empty( $hs['ngay_vao_lam'] ) ? (string) $hs['ngay_vao_lam'] : ( $tn ? substr( (string) $tn['luc'], 0, 10 ) : '' );
		$hn = (string) current_time( 'Y-m-d' );
		$moi = '' !== $vao && ( strtotime( $hn ) - strtotime( $vao ) ) <= self::NGAY_MOI * 86400;
		$ra['thu'] = in_array( strtolower( $ma ), self::ds_thu(), true );
		if ( $ra['thu'] ) { $moi = true; }
		$tich = self::so( self::O_TICH );
		$tich = isset( $tich[ strtolower( $ma ) ] ) ? (array) $tich[ strtolower( $ma ) ] : array();

		$viec = array();
		$viec[] = array( 'k' => 'kich', 'ten' => 'Kích hoạt tài khoản', 'mo' => 'Đăng nhập lần đầu bằng PIN', 'xong' => true );
		$viec[] = array( 'k' => 'nq', 'ten' => 'Đọc & cam kết Nội quy công ty', 'mo' => count( (array) $nq['muc'] ) . ' mục · khoảng ' . self::phut_doc( $nq ) . ' phút', 'xong' => (bool) $dy );
		if ( $tn && method_exists( 'VHCC_TiepNhan', 'pin_da_doi' ) ) {
			$viec[] = array( 'k' => 'pin', 'ten' => 'Đổi PIN của riêng bạn', 'mo' => 'Tab Tôi → Đổi mật khẩu', 'xong' => VHCC_TiepNhan::pin_da_doi( $ma ) );
		}
		$viec[] = array( 'k' => 'cai', 'ten' => 'Cài app lên màn hình chính', 'mo' => 'Chia sẻ → Thêm vào màn hình chính', 'xong' => ! empty( $tich['cai'] ), 'tuTich' => true );
		$co_cham = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . VHCC_DB::t( 'cham_cong' )
			. ' WHERE ma_nv=%s AND gio_vao_giay IS NOT NULL LIMIT 1', $ma ) );
		$viec[] = array( 'k' => 'cham', 'ten' => 'Chấm vào lượt đầu tiên', 'mo' => 'Tự tích khi bạn bấm Chấm vào', 'xong' => $co_cham );
		if ( $tn ) {
			$viec[] = array( 'k' => 'hd', 'ten' => 'Ký hợp đồng lao động', 'mo' => 'Mở bộ hồ sơ nhận việc → Ký điện tử', 'xong' => ! empty( $tn['nvKy'] ),
				'link' => VHCC_TiepNhan::link_bo( $ma ) );
		}
		$xong = 0;
		foreach ( $viec as $v ) { if ( $v['xong'] ) { $xong++; } }
		$ra['viec'] = $viec; $ra['xong'] = $xong; $ra['tong'] = count( $viec ); $ra['moi'] = $moi;
		$ra['hien'] = $moi && empty( $tich['an'] );
		/* Không thấy bảng mà chưa đồng ý bản hiện hành → một dòng nhắc. Chỉ nhắc khi nội quy đã được
		   công ty lưu thật (bản nháp minh hoạ không làm phiền người cũ) hoặc người ấy từng đồng ý bản cũ. */
		$ra['nhacLai'] = ! $ra['hien'] && ! $dy && ( '' !== (string) $nq['luc'] || null !== self::dong_y_cua( $ma ) );
		return $ra;
	}

	/** Danh sách đồng ý cho màn quản trị (mới nhất trước). */
	public static function ds_dong_y( $so_luong = 50 ) {
		$ra = array();
		foreach ( self::so( self::O_DY ) as $ma => $ds ) {
			foreach ( (array) $ds as $x ) { $x['ma'] = strtoupper( $ma ); $ra[] = $x; }
		}
		usort( $ra, function ( $a, $b ) { return strcmp( (string) $b['luc'], (string) $a['luc'] ); } );
		return array_slice( $ra, 0, $so_luong );
	}
}
