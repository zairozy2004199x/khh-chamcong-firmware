/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * NÚT ĐI SANG LOẠI ĐƠN KIA — và canh dải 🎭 Giả lập vai trò dựng cho chặt
 *
 * Anh Thắng 12/09/2026, hai việc trong một mạch:
 *   *"Đối với nhân viên cơ sở ẩn nút này đi, tránh nhập nhầm"*  (nút "← Quay lại chi phí Kỹ thuật")
 *
 * =============================================================================================
 * 🔴 CHỖ HỞ THẬT CỦA CÁI NÚT: `vis.duan` chỉ bị hạ xuống 0 khi ô Bộ phận CÓ KHAI gì đó. Tài
 *    khoản để trống ô ấy — đúng như ảnh anh Thắng gửi (Nguyễn Văn Bin · Nhân viên · FARM PHAN
 *    THIẾT, không bộ phận) — giữ nguyên `vis.duan = 1`, và nút sáng lên mời họ sang màn không
 *    phải việc của mình.
 *
 * 🔴 DẢI "👁 XEM NHƯ" ĐÃ GỠ ngày 22/09/2026 (*"bỏ này đi"*). Mục 2 của bài này nay canh chiều
 *    NGƯỢC LẠI: không một mẩu nào của nó còn sót ở bất cứ bản nào trong bốn bản.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY.
 *
 * Chạy: node tools/test/kiem-nut-chuyen-don.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}
function bocDong(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n', i);
  return (j > i) ? HTML.slice(i, j) : '';
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. NÚT ĐI SANG LOẠI ĐƠN KIA — HAI LỚP GÁC
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. 🔴 CẶP NÚT CHUYỂN ĐƠN ĐÃ GỠ — 22/09/2026
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng: *"Bỏ cái chi phí kỹ thuật đi"*, gửi kèm ảnh chính hai cái nút ấy.
 *
 * Chúng là MỘT CẶP đi liền nhau: "← Quay lại chi phí Kỹ thuật" trên màn đơn tuần, và "← Quay
 * lại đơn tuần của cơ sở" trên màn Kỹ thuật. Gỡ một cái thôi là cái còn lại dẫn người ta sang
 * một nơi không có đường về — nên gỡ cả cặp.
 *
 * Khối phép ở đây TRƯỚC KIA canh luật ẩn/hiện của cặp nút (hai lớp gác, theo vai và theo bộ
 * phận). Nay đảo chiều thành: không một mẩu nào còn sót. Xoá phép đi thì lần sau ai dựng lại
 * cũng không ai hay.
 *
 * ⚠️ VÀ CANH CHIỀU NGƯỢC LẠI NỮA — ĐỪNG GỠ LẠM. `BP_VAO_DUAN` với `_vaoDonCoSo()` PHẢI CÒN:
 *    chúng quyết định TAB nào mở cho ai (`applyPerms()`), và đó mới là chốt quyền thật. Hàm
 *    vừa gỡ chỉ mượn chúng để bày một lối tắt. Quét sạch cả cụm là nhân viên cơ sở mất tab
 *    đơn tuần — hỏng nặng hơn hẳn cái nút vừa gỡ.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const DAU_VET_NUT = ['_veNutChuyenDon', 'data-dcsw-di',
                     'Quay lại chi phí Kỹ thuật', 'Quay lại đơn tuần của cơ sở'];

const BAN = ['vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp'];
/**
 * ⚠️ TƯỚC CHÚ THÍCH TRƯỚC KHI SOI.
 *
 * Bia mộ và chú thích thiết kế có NHẮC TÊN thứ đang soi — soi chuỗi trên nguyên tệp là bài
 * kiểm tự bắt chính lời ghi chú của mình. Chữa bằng cách xoá chú thích thì mất luôn lời dặn;
 * chữa đúng là chỉ soi phần MÃ CHẠY. (Giữ `https://` — `//` trong địa chỉ không phải chú thích.)
 */
function chiMaChay(h) {
  return h
    .replace(/<!--[\s\S]*?-->/g, ' ')
    .replace(/\/\*[\s\S]*?\*\//g, ' ')
    .replace(/(^|[^:])\/\/[^\n]*/g, '$1');
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🎭 GIẢ LẬP VAI TRÒ — DỰNG LẠI 22/09/2026, VÀ PHẢI DỰNG CHO CHẶT
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Mục này TRƯỚC KIA đòi dải "👁 Xem như" phải VẮNG (anh Thắng: *"bỏ này đi"*). Cùng ngày anh
 * đổi ý và nói rõ hơn ý mình: *"Cho admin giả lập vai trò để check"*, rồi *"làm luôn đi em"*.
 * Nên khối phép ở đây đảo chiều — nhưng KHÔNG đảo thành "có là được".
 *
 * 🔴 BA CHỐT PHẢI CÒN, vì thiếu cái nào cũng ra một cái bẫy im lặng:
 *
 *   (a) VAI THẬT GIỮ RIÊNG. Dải hỏi `_laAdminThat()` chứ không hỏi `_laAdmin()`. Hỏi nhầm thì
 *       lúc đội lốt "Nhân viên", `CURUSER.role` hết là Admin -> dải TỰ ẨN, và cái nút Thoát
 *       duy nhất biến mất cùng nó. Còn F5, nhưng bắt người ta tự đoán ra là bẫy, không phải
 *       đường thoát.
 *
 *   (b) KHÔNG LƯU LỐT Ở MÁY KHÁCH. Lưu vào localStorage/sessionStorage là mai mở máy ra vẫn
 *       đang đội lốt mà không nhớ — rồi kết luận sai về màn của chính mình. Không lưu thì F5
 *       luôn là đường về, chắc hơn mọi cái nút.
 *
 *   (c) NÓI THẲNG GIỚI HẠN. Bia mộ của dải cũ dặn đúng một câu: *"dải cũ không đổi quyền ở
 *       máy chủ, nên nó chưa bao giờ trả lời được câu NGƯỜI ẤY BẤM THÌ CÓ BỊ CHẶN KHÔNG"*.
 *       Bản này cũng thế — và khác ở chỗ nó IN RA điều ấy. Dải im lặng là để người ta tin
 *       mình vừa kiểm xong phân quyền trong khi chưa kiểm gì cả. Phép dưới canh đúng dòng chữ.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
BAN.forEach(function (b) {
  const f = 'wordpress/' + b + '/templates/app.html';
  if (!fs.existsSync(f)) { t('có ' + f, false); return; }
  const raw = fs.readFileSync(f, 'utf8');
  const h = chiMaChay(raw);

  DAU_VET_NUT.forEach(function (d) {
    t('🔴 ' + b + ': không còn dấu vết cặp nút chuyển đơn — `' + d + '`', h.indexOf(d) < 0,
      h.indexOf(d) < 0 ? undefined : h.slice(Math.max(0, h.indexOf(d) - 50), h.indexOf(d) + 50));
  });
  /* 🔴 ĐỪNG GỠ LẠM: hai chốt TAB phải còn nguyên. */
  t('🔴 ' + b + ': `BP_VAO_DUAN` VẪN còn — nó gác TAB, không phải cái nút vừa gỡ',
    h.indexOf('BP_VAO_DUAN') >= 0);
  t('🔴 ' + b + ': `_vaoDonCoSo()` VẪN còn — mất là nhân viên cơ sở hết tab đơn tuần',
    h.indexOf('function _vaoDonCoSo') >= 0);

  t('🎭 ' + b + ': có dải giả lập vai trò', h.indexOf('id="glBar"') >= 0);
  ['glDoi', 'glThoat', 'glVeBar', '_laAdminThat', '_glVaiDs'].forEach(function (fn) {
    t(b + ': có ' + fn + '()', h.indexOf('function ' + fn + '(') >= 0);
  });

  // (a) 🔴 hỏi vai THẬT, không hỏi cái lốt
  const bar = h.slice(h.indexOf('function glVeBar('), h.indexOf('function glVeBar(') + 700);
  t('🔴 ' + b + ': dải hỏi `_laAdminThat()` — hỏi `_laAdmin()` là đội lốt xong mất nút Thoát',
    /_laAdminThat\(\)/.test(bar) && !/[^t]_laAdmin\(\)/.test(bar), bar.slice(0, 300));
  const thoat = h.slice(h.indexOf('function glThoat('), h.indexOf('function glThoat(') + 500);
  t('🔴 ' + b + ': `glThoat()` KHÔNG tự khoá theo vai đang đội — nó là đường ra cuối cùng',
    !/_laAdmin\(\)/.test(thoat), thoat.slice(0, 300));
  /* ⚠️ SOI NGUYÊN PHÉP GÁN, không soi mỗi `GL_THAT.role`. Dòng ngay dưới có `GL_THAT.roleGoc`,
     nên phép lỏng khớp phải nó và lượt đục "thoát về vai GÕ CỨNG" đi lọt. */
  t(b + ': và nó trả lại đúng vai thật đã chụp, không phải một vai gõ cứng',
    /CURUSER\.role\s*=\s*GL_THAT\.role\s*;/.test(thoat), thoat.slice(0, 300));

  // (b) 🔴 không lưu lốt ở máy khách
  const gl = h.slice(h.indexOf('var GL_THAT'), h.indexOf('function glThoat(') + 600);
  t('🔴 ' + b + ': KHÔNG lưu lốt vào localStorage / sessionStorage — F5 phải là đường về',
    !/localStorage|sessionStorage/.test(gl), gl.slice(0, 300));

  // (c) 🔴 dải nói thẳng giới hạn — soi trên BẢN GỐC vì đây là chữ hiện cho người đọc
  const i = raw.indexOf('id="glBar"');
  const khoi = i < 0 ? '' : raw.slice(i, i + 1600);
  t('🔴 ' + b + ': dải NÓI RÕ nó không đổi quyền ở máy chủ',
    /KHÔNG<\/b> đổi quyền ở máy chủ/.test(khoi), khoi.slice(0, 200));
  t('🔴 ' + b + ': và nói rõ F5 là về chính mình',
    /F5 là về lại chính mình/.test(khoi), khoi.slice(0, 200));
  t(b + ': có nút thôi giả lập', /glThoat\(\)/.test(khoi));
  /* 🔴 Ô CHỌN GOM BẰNG `<optgroup>`, nhãn nhóm là tên vai cha. */
  const bar2 = h.slice(h.indexOf('function glVeBar('), h.indexOf('function glVeBar(') + 1400);
  /* 🔴 Gói khởi động phải CHỞ vai tự tạo xuống — không thì mã bên màn đúng mà vẫn rỗng, vì
     `CFG` chưa nạp lúc dải vẽ. Soi cả bốn bản: bản vùng sinh lại từ gốc, sót một bản là mất
     tính năng không ai biết. */
  const fDon = 'wordpress/' + b + '/includes/class-vhcp-don.php';
  t('🔴 ' + b + ': gói khởi động CHỞ vai tự tạo xuống màn',
    fs.existsSync(fDon) && /'vaiTuyBien'\s*=>\s*VHCP\w*_Cfg::vai_tuy_bien\(\)/.test(fs.readFileSync(fDon, 'utf8')), '');
  t('🔴 ' + b + ': ô chọn vai GOM theo vai cha bằng optgroup',
    /<optgroup label="/.test(bar2) && /_glVaiNhom\(\)/.test(bar2), bar2.slice(0, 300));

  // chip phải nói ra khi đang đội lốt
  const j = h.indexOf("el('userChip').innerHTML");
  /* ⚠️ SOI ĐÚNG CÁI ĐIỀU KIỆN, không soi mỗi chữ `GL_THAT`: thân nhánh có `GL_THAT.name`, nên
     phép lỏng vẫn xanh dù điều kiện đã bị vặn thành `false` — nhánh còn đó mà không bao giờ
     chạy. Đúng kiểu "xanh vì lý do sai" đã mắc nhiều lần trong phiên này. */
  t('🔴 ' + b + ': chip NÓI RA khi đang đội lốt — chip im lặng là quên mất mình đang giả lập',
    j >= 0 && /innerHTML\s*=\s*GL_THAT\s*\?/.test(h.slice(j, j + 400)), h.slice(j, j + 200));
});

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2b. 🔴 CHẠY THẬT `_glVaiDs()` VỚI `CFG = null` — Ô CHỌN VAI TỪNG RỖNG TRƠN
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 22/09/2026 gửi ảnh dải vừa dựng: ô chọn vai XỔ RA MỘT DANH SÁCH TRẮNG.
 *
 * Nguyên nhân: `var CFG = null` (không phải `{}`), mà `glVeBar()` chạy từ `applyPerms()` — tức
 * NGAY SAU KHI ĐĂNG NHẬP, trước khi ai bấm vào tab Cấu hình. Đọc thẳng `CFG.vaiTro` lúc ấy ném
 * TypeError, `glVeBar()` chết giữa chừng, `sel.innerHTML` chưa kịp đặt.
 *
 * 🔴 VÀ LỖI NÀY KHÔNG KÊU TIẾNG NÀO trên màn — nó chỉ nằm ở bảng điều khiển trình duyệt, nơi
 *    không ai mở. Thứ duy nhất nhìn thấy là một cái ô rỗng, mà ô rỗng thì trông như "chưa khai
 *    vai nào" chứ không như một lỗi.
 *
 * ⚠️ PHẢI CHẠY, KHÔNG ĐƯỢC ĐỌC CHỮ. Mọi phép soi chuỗi ở mục 2 đều XANH suốt lượt hỏng ấy: hàm
 *    vẫn nằm đó, dải vẫn nằm đó, chữ vẫn đủ. Chỉ khi GỌI NÓ với `CFG = null` mới thấy.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
(function () {
  const vm = require('vm');
  const h = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
  function bocHam(ten) {
    const i = h.indexOf('function ' + ten + '(');
    if (i < 0) return '';
    let j = h.indexOf('{', i), d = 0;
    for (let k = j; k < h.length; k++) {
      if (h[k] === '{') d++;
      else if (h[k] === '}') { d--; if (!d) return h.slice(i, k + 1); }
    }
    return '';
  }
  const VG = ['Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên'];

  /* (a) 🔴 ĐÚNG NGUYÊN TRẠNG ANH THẮNG GẶP: vừa đăng nhập xong, chưa ai mở Cấu hình. */
  const c1 = { CFG: null, VAI_GOC: VG };
  vm.createContext(c1);
  let noC1 = '';
  try { vm.runInContext([bocHam('_vaiTuyBienDs'), bocHam('_glVaiNhom'), bocHam('_glVaiDs')].join('\n'), c1); c1.__ra = c1._glVaiDs(); }
  catch (e) { noC1 = String(e && e.message); }
  t('🔴 `CFG` chưa nạp mà gọi `_glVaiDs()` thì KHÔNG được ném lỗi', noC1 === '', noC1);
  t('🔴 và vẫn trả về đủ bốn vai gốc — ô chọn rỗng trông như "chưa khai vai nào", không như lỗi',
    Array.isArray(c1.__ra) && c1.__ra.length === 4, c1.__ra);

  /* (a2) 🔴 CA THẬT CỦA ANH THẮNG: vừa đăng nhập, CHƯA ai bấm vào tab Cấu hình.
     `glVeBar()` vẽ từ `applyPerms()`, tức ngay lúc ấy. Vai tự tạo trước nay chỉ có trong
     `CFG`, mà `CFG` chỉ nạp khi vào Cấu hình — nên ô chọn còn đúng bốn vai gốc, trong khi
     bảng Vai trò tự tạo của anh có MƯỜI vai. Nay `BOOT.vaiTuyBien` chở chúng xuống ngay. */
  const VAI10 = [
    { ten: 'Kỹ Thuật Khu Vui Chơi', goc: 'Nhân viên' },
    { ten: 'Kỹ Thuật Máy Tự Động', goc: 'Nhân viên' },
    { ten: 'Quản Lý Vận Hành KVC', goc: 'Quản lý' },
    { ten: 'Nhân Viên Marketing', goc: 'Nhân viên' },
    { ten: 'Nhân Viên Cơ Sở', goc: 'Nhân viên' },
    { ten: 'Nhân Viên Kho Cơ Sở', goc: 'Nhân viên' },
    { ten: 'Quản Lý Vận Hành MTD', goc: 'Quản lý' },
    { ten: 'Kế Toán MTD', goc: 'Kế toán cá nhân' },
    { ten: 'Kế Toán KVC', goc: 'Kế toán cá nhân' },
    { ten: 'Kế Toán Chung', goc: 'Kế toán cá nhân' },
  ];
  const cA = { CFG: null, BOOT: { vaiTuyBien: VAI10 }, VAI_GOC: VG };
  vm.createContext(cA);
  vm.runInContext([bocHam('_vaiTuyBienDs'), bocHam('_glVaiNhom'), bocHam('_glVaiDs')].join('\n'), cA);
  const raA = cA._glVaiDs();
  t('🔴 CHƯA vào Cấu hình mà vai TỰ TẠO đã đội được — đủ cả mười vai của anh Thắng',
    VAI10.every(v => raA.indexOf(v.ten) >= 0), raA);
  t('   và chúng nằm đúng nhóm vai cha, không dồn vào một chỗ',
    (cA._glVaiNhom().filter(o => o.cha === 'Kế toán cá nhân')[0] || { ds: [] }).ds.indexOf('Kế Toán MTD') >= 0
    && (cA._glVaiNhom().filter(o => o.cha === 'Nhân viên')[0] || { ds: [] }).ds.indexOf('Nhân Viên Kho Cơ Sở') >= 0,
    cA._glVaiNhom().map(o => o.cha + ':' + o.ds.length));
  /* ⚠️ CFG vẫn phải THẮNG khi đã nạp: kế toán vừa thêm một vai ở Cấu hình thì thấy ngay,
     khỏi tải lại trang. */
  const cB = { CFG: { vaiTro: [{ ten: 'Vai vừa thêm', goc: 'Nhân viên' }] },
               BOOT: { vaiTuyBien: VAI10 }, VAI_GOC: VG };
  vm.createContext(cB);
  vm.runInContext([bocHam('_vaiTuyBienDs'), bocHam('_glVaiNhom'), bocHam('_glVaiDs')].join('\n'), cB);
  t('🔴 vai vừa thêm ở Cấu hình hiện ngay, khỏi tải lại trang',
    cB._glVaiDs().indexOf('Vai vừa thêm') >= 0, cB._glVaiDs());
  t('   và vai từ gói khởi động không mất đi',
    cB._glVaiDs().indexOf('Kế Toán MTD') >= 0, cB._glVaiDs());
  t('   không tên nào trùng dù hai nguồn có thể chồng nhau',
    cB._glVaiDs().length === new Set(cB._glVaiDs()).size, cB._glVaiDs());

  /* (a3) 🔴 ĐỘI LỐT MỘT VAI CON THÌ PHẢI QUY VỀ ĐÚNG VAI GỐC — ca "Không có chỗ tạo đơn".
     Anh Thắng 22/09/2026 gửi ảnh: đang giả lập "Kỹ Thuật Khu Vui Chơi" mà thanh tab chỉ còn
     ĐÚNG MỘT nút, và nút "＋ Tạo đơn mới" biến mất.

     Đường đi của lỗi: `glDoi()` đặt `roleGoc` bằng `_vaiGocCua(v)`; hàm ấy chỉ đọc `CFG`, mà
     `CFG` chưa nạp -> trả rỗng -> `roleGoc` thành chính tên vai con -> `_vaiLuat()` trả
     "Kỹ Thuật Khu Vui Chơi" -> bảng `vis` không có khoá ấy nên rơi vào nhánh mặc định
     `{don:1}`, và nút Tạo đơn mới (chỉ bày cho Nhân viên / Quản lý / Admin) tắt theo.

     ⚠️ CHẠY CẢ ĐƯỜNG DÂY, không đo mỗi một hàm: `_vaiGocCua` -> `roleGoc` -> `_vaiLuat`. Đo
        lẻ từng hàm thì mỗi hàm đều "đúng" theo phần của nó, y như lúc lỗi này lọt qua. */
  const cC = { CFG: null, BOOT: { vaiTuyBien: VAI10 }, VAI_GOC: VG,
               CURUSER: { role: 'Kỹ Thuật Khu Vui Chơi', roleGoc: '' } };
  vm.createContext(cC);
  vm.runInContext([bocHam('_vaiTuyBienDs'), bocHam('_vaiGocCua'),
                   bocHam('_vaiGoc'), bocHam('_vaiLuat')].join('\n'), cC);
  const gocCon = cC._vaiGocCua('Kỹ Thuật Khu Vui Chơi');
  t('🔴 `CFG` chưa nạp mà vai con VẪN quy được về vai gốc', gocCon === 'Nhân viên', gocCon);
  cC.CURUSER.roleGoc = gocCon || 'Kỹ Thuật Khu Vui Chơi';
  t('🔴 `_vaiLuat()` trả về VAI GỐC, không trả tên vai con',
    cC._vaiLuat() === 'Nhân viên', cC._vaiLuat());
  /* Nút "＋ Tạo đơn mới" bày theo đúng ba vai này — xem `applyPerms()`. */
  t('🔴 nên nút "＋ Tạo đơn mới" HIỆN — đây là thứ anh Thắng bảo mất',
    ['Nhân viên', 'Quản lý', 'Admin'].indexOf(cC._vaiLuat()) >= 0, cC._vaiLuat());
  /* Vai con của Quản lý phải quy về Quản lý, không quy nhầm xuống Nhân viên. */
  t('   vai con của Quản lý quy đúng về "Quản lý"',
    cC._vaiGocCua('Quản Lý Vận Hành KVC') === 'Quản lý', cC._vaiGocCua('Quản Lý Vận Hành KVC'));
  t('   và vai lạ hoắc thì trả rỗng, không bịa ra vai gốc',
    cC._vaiGocCua('Vai không có thật') === '', cC._vaiGocCua('Vai không có thật'));

  /* (b) Nạp xong thì vai TỰ TẠO phải có mặt thêm — không thì dải chỉ đội được vai gốc. */
  /* ⚠️ DỮ LIỆU THỬ PHẢI CÓ CA TRÙNG TÊN. Bản nháp của bài này chỉ có vai tự tạo tên lạ, nên
     gỡ hẳn phép dọn trùng mà bài vẫn xanh — xanh vì dữ liệu dễ, không phải vì mã đúng. Sổ thật
     có đúng ca ấy: kế toán tạo một vai tên "Quản lý" y hệt vai gốc, và ô chọn bày nó hai lần. */
  const c2 = { CFG: { vaiTro: [{ ten: 'Kế Toán KVC', goc: 'Kế toán cá nhân' },
                               { ten: 'Quản lý', goc: 'Quản lý' },
                               { ten: 'Admin', goc: '' }] }, VAI_GOC: VG };
  vm.createContext(c2);
  vm.runInContext([bocHam('_vaiTuyBienDs'), bocHam('_glVaiNhom'), bocHam('_glVaiDs')].join('\n'), c2);
  const ra2 = c2._glVaiDs();
  t('🔴 nạp xong thì vai TỰ TẠO cũng đội được', ra2.indexOf('Kế Toán KVC') >= 0, ra2);

  /* ── 🔴 TÁCH RÕ VAI CON THUỘC VAI CHA NÀO ────────────────────────────────────────────────
     Anh Thắng 22/09/2026: *"tách rõ nhân viên theo vai trò nào luôn"*. Danh sách phẳng bày
     "Nhân viên" cạnh "Nhân Viên Cơ Sở" cạnh "Nhân Viên Kho Cơ Sở" như ba thứ ngang hàng —
     trong khi hai cái sau là CON của cái đầu. Đội nhầm một lốt là kết luận sai về màn của cả
     một nhóm người. */
  const c3 = { CFG: { vaiTro: [
    { ten: 'Nhân Viên Cơ Sở', goc: 'Nhân viên' },
    { ten: 'Nhân Viên Kho Cơ Sở', goc: 'Nhân viên' },
    { ten: 'Quản Lý Vận Hành', goc: 'Quản lý' },
    { ten: 'Vai lạc', goc: '' },
    { ten: 'Admin', goc: '' },
  ] }, VAI_GOC: VG };
  vm.createContext(c3);
  vm.runInContext([bocHam('_vaiTuyBienDs'), bocHam('_glVaiNhom'), bocHam('_glVaiDs')].join('\n'), c3);
  const nh = c3._glVaiNhom();
  const tim = c => (nh.filter(o => o.cha === c)[0] || { ds: [] }).ds;

  t('🔴 vai con nằm ĐÚNG nhóm của vai cha nó kế thừa',
    tim('Nhân viên').indexOf('Nhân Viên Cơ Sở') >= 0
    && tim('Nhân viên').indexOf('Nhân Viên Kho Cơ Sở') >= 0, tim('Nhân viên'));
  t('   và KHÔNG lẫn sang nhóm khác',
    tim('Quản lý').indexOf('Nhân Viên Cơ Sở') < 0
    && tim('Quản lý').indexOf('Quản Lý Vận Hành') >= 0, tim('Quản lý'));
  t('🔴 vai GỐC đứng đầu nhóm của chính nó — đội "Nhân viên" trần vẫn là một lựa chọn thật',
    tim('Nhân viên')[0] === 'Nhân viên', tim('Nhân viên'));
  /* Vai mồ côi hay sai nhất, nên càng phải thử được — bỏ đi là mất hẳn lối thử. */
  const cuoi = nh[nh.length - 1];
  t('🔴 vai MỒ CÔI (chưa khai cha) dồn xuống nhóm cuối, KHÔNG bị bỏ đi',
    cuoi.ds.indexOf('Vai lạc') >= 0 && cuoi.cha !== 'Nhân viên', cuoi);
  t('   và không nhóm nào rỗng — nhóm rỗng là một nhãn trống trong ô chọn',
    nh.every(o => o.ds.length > 0), nh.map(o => o.cha + ':' + o.ds.length));
  t('🔴 bản PHẲNG lấy từ chính bản NHÓM — hai danh sách riêng là có ngày lệch nhau',
    c3._glVaiDs().length === nh.reduce((n, o) => n + o.ds.length, 0), c3._glVaiDs());
  t('🔴 nhưng KHÔNG bày "Admin" — đội lốt chính mình là một lựa chọn vô nghĩa',
    ra2.indexOf('Admin') < 0, ra2);
  t('   và không có tên nào trùng', ra2.length === new Set(ra2).size, ra2);
})();

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. DỰNG LẠI, NHƯNG ĐỪNG ĐỤNG HAI THỨ BÊN CẠNH
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 `boot()` gọi `_applyTabPerms()` — mất là tab khoá cứng sau khi đăng nhập.
 * 🔴 Chip tên người dùng vẫn phải nói đủ tên · vai khi KHÔNG giả lập; gỡ nhầm nhánh ấy là góc
 *    trên màn trống trơn, trông y như chưa đăng nhập.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
BAN.forEach(function (b) {
  const f = 'wordpress/' + b + '/templates/app.html';
  if (!fs.existsSync(f)) { return; }
  const h = fs.readFileSync(f, 'utf8');
  /* ⚠️ Từ 1.272.0 `boot()` gọi thêm `_napKhoiDs()` ngay giữa — nên đừng ghim nguyên cả cụm
     lời gọi. Canh cái cần canh: nhận gói khởi động xong thì MỞ TAB theo phân quyền. */
  t('🔴 ' + b + ': `boot()` VẪN mở tab theo phân quyền',
    /BOOT=b\|\|BOOT;[^\n]*_applyTabPerms\(\);/.test(h));
  const i = h.indexOf("el('userChip').innerHTML");
  t('🔴 ' + b + ': chip VẪN nói đủ tên · vai khi không giả lập',
    i >= 0 && /esc\(CURUSER\.name\)/.test(h.slice(i, i + 500)) && /esc\(role\)/.test(h.slice(i, i + 500)),
    i >= 0 ? h.slice(i, i + 200) : '(không thấy chip)');
});

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — nút gác hai lớp, và dải giả lập vai trò dựng đúng ở cả bốn bản');
