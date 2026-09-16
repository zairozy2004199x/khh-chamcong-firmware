<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BỘ SKILL TRONG `.claude/skills/` — CANH HAI THỨ: ĐỌC ĐƯỢC, VÀ KHÔNG LÉN CHẠY GÌ.
 *
 * Anh Thắng 16/09/2026 gửi video liệt kê tám repo mã nguồn mở, bảo *"Tải và nạp các repo này
 * cho anh để làm skill"*. Chỉ một trong tám là skill thật (addyosmani/agent-skills, 25 skill
 * Markdown); bảy bộ kia là phần mềm chạy độc lập — xem `.claude/skills/README-NGUON.md`.
 *
 * ==============================================================================================
 * 🔴 VÌ SAO PHẢI CANH — SKILL KHÔNG PHẢI THƯ VIỆN.
 *
 *    Thư viện là chữ MÁY chạy: sai thì nổ, thấy ngay. Skill là chữ CLAUDE ĐỌC RỒI LÀM THEO —
 *    nó đổi cách viết mã, cách sửa bài kiểm, cách bấm nút, mà không có một dòng lỗi nào. Một
 *    lượt nâng bản lặng lẽ thêm mấy câu vào SKILL.md là đổi hành vi của mọi phiên làm việc sau
 *    đó, và không ai thấy cho tới lúc có gì đó lạ.
 *
 * ⚠️ HAI PHÉP NÀY KHÔNG THAY CHO VIỆC ĐỌC `diff`. Chúng chỉ chặn hai kiểu hỏng ĐẾM ĐƯỢC:
 *    skill câm (thiếu name/description thì Claude Code bỏ qua, im lặng), và tệp CHẠY ĐƯỢC lọt
 *    vào. Nội dung chữ thì vẫn phải đọc bằng mắt — xem README-NGUON.md.
 *
 * Chạy: php tools/test/kiem-bo-skill.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
$thu = $goc . '/.claude/skills';
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = '' ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( '' === $them ? '' : "\n      → " . $them );
}

if ( ! is_dir( $thu ) ) {
	echo "\n⚠️ BỎ QUA — chưa có .claude/skills/ trên cây mã này.\n";
	exit( 0 );
}

/* ═══ 1. MỖI SKILL PHẢI ĐỌC ĐƯỢC ═══════════════════════════════════════════════
 * Claude Code tìm `.claude/skills/<tên>/SKILL.md` và đọc `name` + `description` ở đầu tệp.
 * Thiếu một trong hai thì skill ấy KHÔNG BAO GIỜ được gọi — và không có câu lỗi nào, nó chỉ
 * đơn giản là không tồn tại. Đúng kiểu hỏng im lặng mà cả bộ thử này sinh ra để dẹp. */
$bo = array();
foreach ( (array) glob( $thu . '/*', GLOB_ONLYDIR ) as $d ) { $bo[] = $d; }
t( '🔴 có ít nhất 20 skill', count( $bo ) >= 20, count( $bo ) . ' bộ' );

$cam = array();
$ten_da_co = array();
foreach ( $bo as $d ) {
	$ten = basename( $d );
	$f   = $d . '/SKILL.md';
	if ( ! is_file( $f ) ) { $cam[] = $ten . ' (không có SKILL.md)'; continue; }
	$ma = (string) file_get_contents( $f );
	if ( ! preg_match( '/^name:\s*(\S.*)$/m', $ma, $m_n ) ) { $cam[] = $ten . ' (thiếu name)'; continue; }
	if ( ! preg_match( '/^description:\s*(\S.*)$/m', $ma ) ) { $cam[] = $ten . ' (thiếu description)'; continue; }
	/* 🔴 `name` PHẢI BẰNG ĐÚNG TÊN THƯ MỤC. Lệch thì gọi bằng tên nào cũng trượt một nửa —
	   và đó là kiểu lỗi chỉ lộ ra lúc đang cần dùng. */
	$n = trim( $m_n[1] );
	if ( $n !== $ten ) { $cam[] = $ten . ' (name khai là "' . $n . '")'; continue; }
	if ( isset( $ten_da_co[ $n ] ) ) { $cam[] = $ten . ' (trùng tên)'; continue; }
	$ten_da_co[ $n ] = true;
}
t( '🔴 không skill nào câm (đủ SKILL.md · name · description, name = tên thư mục)',
	! $cam, implode( ' · ', $cam ) );

/* ═══ 2. KHÔNG TỆP NÀO LÉN CHẠY ĐƯỢC ═══════════════════════════════════════════
 * 🔴 Skill là chữ để ĐỌC. Một tệp chạy được nằm lẫn trong đó là mã của người lạ trong repo
 *    của mình — và nó đi theo mọi lượt `git clone`, mọi máy, mọi phiên.
 *
 * ⚠️ MỘT TỆP ĐƯỢC PHÉP, và chỉ một: `idea-refine/scripts/idea-refine.sh` — 15 dòng, chỉ
 *    `mkdir -p docs/ideas`, đã đọc từng dòng ngày 16/09/2026. Khai đích danh ở đây chứ không
 *    khai "cho phép thư mục scripts": bản sau mọc thêm tệp nào thì phép này ĐỎ, và đó đúng là
 *    lúc phải có người đọc nó. */
$CHO_PHEP = array( 'idea-refine/scripts/idea-refine.sh' );
$la = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $thu ) );
foreach ( $it as $f ) {
	if ( ! $f->isFile() ) { continue; }
	$duong = str_replace( $thu . '/', '', $f->getPathname() );
	$duoi  = strtolower( $f->getExtension() );
	if ( 'md' === $duoi || 0 === strpos( basename( $duong ), 'LICENSE' ) ) { continue; }
	if ( in_array( $duong, $CHO_PHEP, true ) ) { continue; }
	$la[] = $duong;
}
t( '🔴 không tệp chạy được nào ngoài danh sách đã đọc', ! $la,
	implode( ' · ', $la ) . ' — ĐỌC TỪNG DÒNG rồi mới khai vào $CHO_PHEP' );

/* ⚠️ VÀ TỆP ĐƯỢC PHÉP ẤY PHẢI ĐÚNG LÀ CÁI ĐÃ ĐỌC. Khai tên vào danh sách rồi để nội dung
   đổi tự do thì danh sách thành một cái cửa mở sẵn. */
$sh = $thu . '/idea-refine/scripts/idea-refine.sh';
if ( is_file( $sh ) ) {
	$ma_sh = (string) file_get_contents( $sh );
	t( '⚠️ script được phép vẫn chỉ tạo thư mục, không đụng mạng / xoá / quyền',
		! preg_match( '#\b(curl|wget|ssh|sudo|chmod|eval|base64)\b|rm\s+-rf|/dev/tcp|\|\s*(sh|bash)\b#', $ma_sh ),
		'script đã đổi nội dung — đọc lại từng dòng' );
	t( '⚠️ và vẫn ngắn như lúc đọc (dưới 30 dòng)',
		substr_count( $ma_sh, "\n" ) < 30, substr_count( $ma_sh, "\n" ) . ' dòng' );
}

/* ═══ 3. NGUỒN VÀ GIẤY PHÉP PHẢI Ở LẠI ═════════════════════════════════════════
 * Bộ này giấy phép MIT — MIT đòi giữ nguyên dòng bản quyền khi phát tán lại, mà repo này thì
 * `git clone` được. Mất tệp giấy phép là phát tán lại sai luật. */
t( '🔴 giữ tệp giấy phép MIT của bộ gốc', is_file( $thu . '/LICENSE-agent-skills' ) );
$rd = $thu . '/README-NGUON.md';
t( '🔴 có ghi chú nói rõ lấy từ đâu', is_file( $rd ) );
if ( is_file( $rd ) ) {
	$ma_rd = (string) file_get_contents( $rd );
	t( '   nêu đích danh repo gốc',
		false !== strpos( $ma_rd, 'addyosmani/agent-skills' ), '' );
	/* ⚠️ CHỐT SỐ COMMIT. Không có nó thì "nâng bản" là so với hư không — không ai biết bản
	   đang nằm đây là bản nào để mà đọc `diff`. */
	t( '   chốt đúng số commit đang dùng', (bool) preg_match( '/\bbe4e44a\b/', $ma_rd ), '' );
	t( '   và nói vì sao KHÔNG dùng npx skills add',
		false !== strpos( $ma_rd, 'npx skills add' ), '' );
}

/* ═══════════════════════════════════════════════════════════════════════════════
 * Khối báo trượt đứng CUỐI CÙNG — thêm mục mới thì thêm Ở TRÊN chỗ này.
 * ═══════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: " . count( $bo ) . " skill đều đọc được, không tệp lạ nào chạy được.\n";
