/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * VAI CHUNG (VAI GỐC) THÔI LÀ Ô TÍCH — VÀ DỮ LIỆU CŨ KHÔNG BỊ XOÁ LẶNG LẼ.
 *
 * Anh Thắng 22/09/2026: *"Vai trò đang tích lẻ, nên vai trò chung không dùng nữa"*.
 *
 * =============================================================================================
 * 🔴 VÌ SAO Ô TÍCH VAI GỐC LÀ MỘT CÁI BẪY
 * =============================================================================================
 * `_vaiDungDuocLoai()` so TÊN VAI người ta đang mang với danh sách vai của loại, và vai gốc
 * KHÔNG tự suy ra cho vai con (có phép canh hẳn hoi ở `kiem-loai-bang-dau-muc.js`). Nên một
 * loại chỉ tích "Quản lý" mà không tích vai con nào thì MỌI quản lý thật — ai cũng mang một
 * vai con như "Quản Lý Khu Vui Chơi" — đều không thấy loại ấy.
 *
 * Một ô tích trông như mở quyền cho cả nhóm mà thật ra đóng sạch là cái bẫy tệ nhất trong bảng
 * này: không báo lỗi, không mất dữ liệu, chỉ là kế toán mở ô chọn ra và loại chi phí không có
 * ở đó.
 *
 * 🔴 NHƯNG BỎ Ô TÍCH RA KHỎI DOM LÀ XOÁ DỮ LIỆU. Lượt Lưu đọc `[data-vai] input:checked`; ô
 *    không còn trong DOM thì vai ấy biến khỏi sổ ngay lượt Lưu kế tiếp — mà người bấm Lưu chỉ
 *    định sửa một ô khác hẳn. Nên vai gốc ĐANG TÍCH vẫn nằm đó dưới dạng ô tích GIẤU ĐI, kèm
 *    dòng nhắc và hai đường gỡ DO NGƯỜI BẤM.
 *
 * ⚠️ BÀI NÀY CHẠY THẬT ba nút ấy trên một cây DOM nhỏ dựng từ CHÍNH chuỗi HTML mà
 *    `_vaiSelNhieu()` sinh ra — soi chữ thì không bắt được "bấm xong không đổi gì".
 *
 * Chạy: node tools/test/kiem-vai-chung-da-bo.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? ('\n      → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const GOC = path.resolve(__dirname, '..', '..');
const BAN = ['vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp'];

/* ── Cây DOM tí hon ────────────────────────────────────────────────────────────────────────
   Chỉ đủ cho những gì ba hàm kia sờ tới: getAttribute · querySelector(All) · removeChild ·
   `checked` · `value` · `textContent`. Dựng từ chuỗi HTML thật, không bịa cấu trúc — bịa là
   bài kiểm xanh trên một cái cây không có ngoài đời. */
const RONG = { input: 1, br: 1, img: 1, hr: 1 };
function phanTich(html) {
  const goc = lamNut('#root', {});
  let nay = goc, i = 0;
  while (i < html.length) {
    const lt = html.indexOf('<', i);
    if (lt < 0) { nay.text += html.slice(i); break; }
    if (lt > i) nay.text += html.slice(i, lt);
    const gt = html.indexOf('>', lt);
    if (gt < 0) break;
    const the = html.slice(lt + 1, gt);
    i = gt + 1;
    if (the[0] === '/') { if (nay.parentNode) nay = nay.parentNode; continue; }
    const ten = (the.match(/^[a-zA-Z0-9]+/) || [''])[0].toLowerCase();
    const attrs = {};
    (the.slice(ten.length).match(/[a-zA-Z-]+(="[^"]*")?/g) || []).forEach(function (kv) {
      const j = kv.indexOf('=');
      if (j < 0) attrs[kv.toLowerCase()] = '';
      else attrs[kv.slice(0, j).toLowerCase()] = kv.slice(j + 2, -1);
    });
    const nut = lamNut(ten, attrs);
    nut.parentNode = nay; nay.con.push(nut);
    if (!RONG[ten] && the[the.length - 1] !== '/') nay = nut;
  }
  return goc;
}
function lamNut(ten, attrs) {
  const n = { tag: ten, attrs: attrs, con: [], parentNode: null, text: '' };
  n.getAttribute = function (k) { return Object.prototype.hasOwnProperty.call(attrs, k) ? attrs[k] : null; };
  n.setAttribute = function (k, v) { attrs[k] = String(v); };
  n.checked = Object.prototype.hasOwnProperty.call(attrs, 'checked');
  n.disabled = Object.prototype.hasOwnProperty.call(attrs, 'disabled');
  n.value = attrs.value || '';
  Object.defineProperty(n, 'textContent', {
    get: function () { return n.text + n.con.map(function (c) { return c.textContent; }).join(''); },
    set: function (v) { n.text = String(v); n.con.length = 0; },
  });
  n.removeChild = function (c) { const j = n.con.indexOf(c); if (j >= 0) n.con.splice(j, 1); return c; };
  n.querySelectorAll = function (sel) { const ra = []; quet(n, sel, ra); return ra; };
  n.querySelector = function (sel) { return n.querySelectorAll(sel)[0] || null; };
  return n;
}
/* Bộ chọn tí hon: `tag`, `[attr]`, `[attr="v"]`, `:not([attr])` — đúng những dạng mã thật dùng.
   🔴 KHÔNG cho qua bộ chọn lạ. Trả bừa là đục hỏng bộ chọn trong mã thật mà bài vẫn xanh. */
function khop(nut, sel) {
  let s = sel.trim();
  const tag = (s.match(/^[a-zA-Z0-9]+/) || [''])[0];
  if (tag) { if (nut.tag !== tag.toLowerCase()) return false; s = s.slice(tag.length); }
  let m;
  const khongRe = /:not\(\[([a-zA-Z-]+)\]\)/g;
  while ((m = khongRe.exec(s))) { if (nut.getAttribute(m[1]) !== null) return false; }
  s = s.replace(khongRe, '');
  const coRe = /\[([a-zA-Z-]+)(="([^"]*)")?\]/g;
  while ((m = coRe.exec(s))) {
    const v = nut.getAttribute(m[1]);
    if (v === null) return false;
    if (m[3] !== undefined && v !== m[3]) return false;
  }
  const con = s.replace(coRe, '').trim();
  if (con) throw new Error('bộ chọn chưa hỗ trợ: ' + sel);
  return true;
}
function quet(nut, sel, ra) {
  nut.con.forEach(function (c) { if (khop(c, sel)) ra.push(c); quet(c, sel, ra); });
}

/* ── Bộ gieo vai ───────────────────────────────────────────────────────────────────────── */
const VAI_MAU = [
  { ten: 'Quản Lý Khu Vui Chơi', goc: 'Quản lý' },
  { ten: 'Quản Lý Máy Tự Động', goc: 'Quản lý' },
  { ten: 'Kế Toán Khu Vui Chơi', goc: 'Kế toán cá nhân' },
  { ten: 'Nhân Viên Cơ Sở Khu Vui Chơi', goc: 'Nhân viên' },
];

BAN.forEach(function (ban) {
  const f = path.join(GOC, 'wordpress', ban, 'templates/app.html');
  if (!fs.existsSync(f)) { t('có ' + ban + '/app.html', false); return; }
  const HTML = fs.readFileSync(f, 'utf8');
  const boc = function (ten) {
    const i = HTML.indexOf('function ' + ten + '(');
    t(ban + ': bốc được ' + ten + '()', i >= 0);
    return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
  };
  const bocBien = function (ten) {
    const i = HTML.indexOf('  var ' + ten + '=');
    return i < 0 ? '[]' : HTML.slice(HTML.indexOf('=', i) + 1, HTML.indexOf('];', i) + 1);
  };
  const nk = [];
  const F = new Function('moi', 'with(moi){'
    + 'var CFG={vaiTro:VAI_MAU};\n'
    + boc('esc') + '\nvar VAI_GOC=' + bocBien('VAI_GOC') + ';'
    + '\nvar KHOI_THEO_TEN_VAI=' + bocBien('KHOI_THEO_TEN_VAI') + ';'
    + '\n' + ['_boDauVai', '_khoiCuaVai', '_vaiOKhoi', '_vaiConCua', '_vaiSelNhieu',
      'vaiNhomHet', '_vaiHopCua', 'vaiNoChung', 'vaiBoChung', '_vaiSotVe'].map(boc).join('\n')
    + '\nreturn { ve:_vaiSelNhieu, het:vaiNhomHet, no:vaiNoChung, bo:vaiBoChung }; }')({
      VAI_MAU: VAI_MAU, toast: function (k, m) { nk.push([k, m]); },
    });

  /* Dựng hộp rồi phân tích thành cây. Trả về { hop, tich() } — `tich()` đọc những vai đang
     tích ĐÚNG như lượt Lưu đọc: `[data-vai] input:checked`. */
  function dung(v, khoi) {
    const cay = phanTich(F.ve(v, khoi || 'kvc'));
    const hop = cay.querySelector('[data-vai]');
    return { hop: hop,
      tich: function () { return hop.querySelectorAll('input[type="checkbox"]')
        .filter(function (o) { return o.checked; })
        .map(function (o) { return o.value; }).sort(); } };
  }

  /* ═══ 1. VAI GỐC KHÔNG CÒN TÍCH ĐƯỢC ═════════════════════════════════════════════ */
  {
    const d = dung('', 'kvc');
    const oHet = d.hop.querySelectorAll('input[type="checkbox"]').map(function (o) { return o.value; });
    t('🔴 ' + ban + ': không ô tích nào mang tên một vai GỐC',
      ['Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên'].every(function (g) { return oHet.indexOf(g) < 0; }), oHet);
    t('   nhưng vẫn có ô tích cho từng vai CON', oHet.indexOf('Quản Lý Khu Vui Chơi') >= 0, oHet);
    /* 24/09/2026: không còn nhãn nhóm theo vai gốc — anh Thắng: *"Lấy tên vai chứ, lấy cái kế thừa
       quyền chi đâu"*. Mọi vai tự tạo liệt kê thẳng. */
    t('   và vai gốc KHÔNG còn hiện làm nhãn nhóm',
      d.hop.querySelectorAll('b').map(function (b) { return b.textContent; }).indexOf('Quản lý') < 0,
      d.hop.querySelectorAll('b').map(function (b) { return b.textContent; }));
    teq('   đủ MỌI vai tự tạo, đúng thứ tự bảng 🎭', VAI_MAU.map(function (x) { return x.ten; }), oHet);
    t('   loại chưa tích gì thì không có dòng nhắc nào', !d.hop.querySelector('[data-vai-sot]'));
  }

  /* ═══ 2. 🔴 VAI CHUNG CÒN SÓT: GIẤU ĐI, KHÔNG XOÁ ════════════════════════════════
   * Đây là phép quan trọng nhất: `tich()` đọc đúng như lượt Lưu, nên nó trả lời thẳng câu
   * "mở màn ra rồi bấm Lưu thì quyền có mất không". */
  {
    const d = dung('Quản lý, Kế Toán Khu Vui Chơi', 'kvc');
    teq('🔴 ' + ban + ': mở màn rồi Lưu ngay — vai chung KHÔNG mất',
      ['Kế Toán Khu Vui Chơi', 'Quản lý'], d.tich());
    const an = d.hop.querySelectorAll('input[data-vai-chung]');
    teq('   nó nằm trong ô tích GIẤU ĐI, không bày ra cho ai tích mới', 1, an.length);
    t('   và ô ấy thật sự bị giấu', /display:none/.test(an[0].getAttribute('style') || ''), an[0].attrs);
    t('🔴 ' + ban + ': có dòng nhắc để kế toán tự thấy mà gỡ', !!d.hop.querySelector('[data-vai-sot]'));
  }

  /* ═══ 3. NÚT "↧ NỞ RA CÁC VAI CON" — CHẠY THẬT ═══════════════════════════════════ */
  {
    const d = dung('Quản lý', 'kvc');
    const nut = d.hop.querySelector('[data-vai-sot]').querySelectorAll('button')[0];
    nk.length = 0;
    F.no(nut);
    const sau = d.tich();
    t('🔴 ' + ban + ': nở xong thì CẢ HAI vai con của nhóm đều được tích',
      sau.indexOf('Quản Lý Khu Vui Chơi') >= 0 && sau.indexOf('Quản Lý Máy Tự Động') >= 0, sau);
    t('🔴 và vai chung bỏ đi — không để hai lối cùng nói một chuyện',
      sau.indexOf('Quản lý') < 0 && d.hop.querySelectorAll('input[data-vai-chung]').length === 0, sau);
    t('   không đụng tới nhóm khác', sau.indexOf('Kế Toán Khu Vui Chơi') < 0
      && sau.indexOf('Nhân Viên Cơ Sở Khu Vui Chơi') < 0, sau);
    t('   dòng nhắc gỡ theo, không để màn nói một đằng dữ liệu một nẻo',
      !d.hop.querySelector('[data-vai-sot]'), '');
    t('   và có báo cho người bấm biết đã đổi gì', nk.some(function (x) { return x[0] === 'ok'; }), nk);
  }

  /* ═══ 3b. 🔴 NHÓM CHƯA CÓ VAI CON NÀO THÌ GIỮ NGUYÊN, KHÔNG NỞ RA HƯ KHÔNG ═══════
   * Nở trước rồi mới bỏ ô chung — bỏ trước là quyền biến mất sạch mà chẳng ai thay chỗ. */
  {
    const d = dung('Kế toán NCC', 'kvc');   // bộ gieo không có vai con nào thuộc nhóm này
    const nut = d.hop.querySelector('[data-vai-sot]').querySelectorAll('button')[0];
    nk.length = 0;
    F.no(nut);
    teq('🔴 ' + ban + ': nhóm chưa có vai con thì vai chung GIỮ NGUYÊN, không bay',
      ['Kế toán NCC'], d.tich());
    t('   và có câu cảnh báo, không im lặng', nk.some(function (x) { return x[0] === 'warn'; }), nk);
    t('   dòng nhắc cũng ở lại', !!d.hop.querySelector('[data-vai-sot]'), '');
  }

  /* ═══ 4. NÚT "✕ BỎ HẲN" — CHẠY THẬT ═════════════════════════════════════════════ */
  {
    const d = dung('Quản lý, Kế Toán Khu Vui Chơi', 'kvc');
    const nut = d.hop.querySelector('[data-vai-sot]').querySelectorAll('button')[1];
    F.bo(nut);
    teq('🔴 ' + ban + ': bỏ hẳn thì vai chung biến, vai con KHÔNG bị đụng',
      ['Kế Toán Khu Vui Chơi'], d.tich());
    t('   dòng nhắc gỡ theo', !d.hop.querySelector('[data-vai-sot]'), '');
  }

  /* ═══ 5. NÚT "✓ hết / ✕ bỏ" — CHẠY THẬT ═══════════════════════════════════════════
   * 24/09/2026: hộp còn MỘT hàng phẳng (anh Thắng: *"Lấy tên vai chứ"*), nên "✓ hết" = tích mọi
   * vai tự tạo, và nó nói rõ thế ở title. Vẫn quét theo HÀNG để không đụng ô chung đang giấu. */
  {
    const d = dung('', 'kvc');
    const nutHet = d.hop.querySelectorAll('button').filter(function (b) {
      return /vaiNhomHet\(this,1\)/.test(b.getAttribute('onclick') || ''); });
    teq(ban + ': đúng MỘT nút ✓ hết cho cả hộp', 1, nutHet.length);
    F.het(nutHet[0], 1);
    const sau = d.tich();
    teq('🔴 ' + ban + ': "✓ hết" tích đủ MỌI vai tự tạo', VAI_MAU.map(function (x) { return x.ten; }).sort(), sau);
    const nutBo = d.hop.querySelectorAll('button').filter(function (b) {
      return /vaiNhomHet\(this,0\)/.test(b.getAttribute('onclick') || ''); });
    F.het(nutBo[0], 0);
    teq('   "✕ bỏ" gỡ hết về trống', [], d.tich());
  }

  /* ═══ 5b. 🔴 "✕ bỏ" KHÔNG ĐƯỢC GỠ Ô CHUNG ĐANG GIẤU ═════════════════════════════
   * Nó có đường gỡ riêng (dòng nhắc). Gỡ ở đây thì dòng nhắc vẫn còn mà ô đã tắt — hai bên
   * nói hai chuyện, và người ta bấm "Nở ra" rồi không thấy gì đổi. */
  {
    const d = dung('Quản lý', 'kvc');
    const nutBo = d.hop.querySelectorAll('button').filter(function (b) {
      return /vaiNhomHet\(this,0\)/.test(b.getAttribute('onclick') || ''); });
    F.het(nutBo[0], 0);
    teq('🔴 ' + ban + ': "✕ bỏ" của nhóm KHÔNG đụng ô chung đang giấu', ['Quản lý'], d.tich());
    t('   và dòng nhắc vẫn còn, khớp với dữ liệu', !!d.hop.querySelector('[data-vai-sot]'), '');
  }
});

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: vai chung thôi tích được, mà dữ liệu cũ không bị xoá lặng lẽ.');
