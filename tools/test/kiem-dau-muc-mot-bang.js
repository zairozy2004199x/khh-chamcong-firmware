/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỖI ĐẦU MỤC MỘT BẢNG — VẼ RỒI LƯU LẠI, KHÔNG DÒNG NÀO ĐƯỢC MẤT KHỐI.
 *
 * Anh Thắng 22/09/2026: *"Mỗi đầu mục là 1 bảng riêng,,"* — sau khi chốt *"Khối là dùng chung,
 * vì đã phân theo vai trò rồi"* và *"Khối là để xác định tài khoản nợ"*.
 *
 * =============================================================================================
 * 🔴 VÌ SAO BÀI NÀY PHẢI CHẠY THẬT CẢ HAI ĐẦU, KHÔNG SOI CHỮ
 * =============================================================================================
 * Tới bản này, trục chia bảng trên đổi từ KHỐI sang ĐẦU MỤC. Nhưng khối KHÔNG chết: bảng mã
 * TK Nợ bên dưới vẫn cắt theo nó. Nghĩa là khối phải đi theo TỪNG DÒNG qua một lượt vẽ rồi lưu
 * lại — mà trước bản này nó đi nhờ mốc `data-khoi` đóng trên tbody.
 *
 * Bỏ sót một đầu của dây chuyền ấy là lượt Lưu đóng TÊN ĐẦU MỤC vào cột `khoi` của mọi dòng:
 * không câu lỗi nào, bảng lưu xong vẽ lại trông vẫn bình thường, chỉ là MỌI loại rơi khỏi bảng
 * mã TK Nợ và mọi mã tài khoản thành vô chủ. Đúng họ với ba lần "vá nửa đường" của tuần này
 * (mất đơn ×2, mất dòng chi ×1) — nên bài này soi ĐỦ CHẶNG: cột trong sổ → hàm vẽ → cây DOM →
 * hàm lưu → kết quả ghi xuống.
 *
 * 🔴 VÀ CANH LUÔN CÁI KHOÁ. Một bảng đầu mục chứa lẫn dòng của cả ba khối, nên khoá phải theo
 *    TỪNG DÒNG. Lại không được tin mỗi cái khoá trên màn: gỡ `disabled` bằng công cụ lập trình
 *    rồi gõ là qua được, nên lượt Lưu phải chép nguyên bản cũ cho dòng của khối lạ.
 *
 * Chạy: node tools/test/kiem-dau-muc-mot-bang.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? ('\n      → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const GOC = path.resolve(__dirname, '..', '..');
/* 🔴 CẢ BỐN BẢN. Bản vùng SINH LẠI từ bản gốc mỗi lượt `tools/tach-ban-vung.sh`, nên một bài
   chỉ soi bản gốc là ba bản còn lại có thể đã lệch mà không ai hay. */
const BAN = ['vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp'];

/* ── Bộ gieo: BỐN đầu mục khác nhau, BA khối khác nhau, cộng hai ca khó ───────────────────
   · một loại CHƯA xếp đầu mục  → phải rơi vào ô hứng, không được biến mất
   · hai loại TRÙNG TÊN khác khối → cả hai phải sống (đó là ý *"tránh dùng chung"*)
   ⚠️ Mỗi dòng mang sẵn `tkNo`/`maDt`/`boPhan` — mấy cột KHÔNG có ô nào trên màn. Chúng là
      thứ đầu tiên bay khi lượt Lưu dò nhầm bản ghi cũ, nên bộ gieo phải có đủ. */
const LOAI = [
  { ten: 'Chi phí NVL đồ ăn', khoi: 'kvc', dauMuc: 'Chi phí cơ sở',    tkNo: '6412', maDt: 'DT1', boPhan: 'Cơ sở',  donVi: 'K&H', tkCo: '331', tenMisa: 'NVL đồ ăn' },
  { ten: 'Chi phí cơ sở',     khoi: 'kvc', dauMuc: 'Chi phí cơ sở',    tkNo: '6413', maDt: 'DT2', boPhan: 'Cơ sở',  donVi: 'K&H' },
  { ten: 'Chi phí chung VP',  khoi: 'vp',  dauMuc: 'Chi phí chung',    tkNo: '6421', maDt: 'DT3', boPhan: 'Kế toán', donVi: 'POSH' },
  { ten: 'Thuê mall',         khoi: 'mtd', dauMuc: 'Chi phí tiền thuê', tkNo: '6417', maDt: 'DT4' },
  { ten: 'Chi phí lặt vặt',   khoi: 'kvc', dauMuc: '',                  tkNo: '6418', maDt: 'DT5' },
  { ten: 'Chi phí khác',      khoi: 'kvc', dauMuc: 'Khác',              tkNo: '6419', maDt: 'DT6' },
  { ten: 'Chi phí khác',      khoi: 'vp',  dauMuc: 'Khác',              tkNo: '6429', maDt: 'DT7' },
];
const DAU_MUC_DS = ['Chi phí chung', 'Chi phí cơ sở', 'Chi phí tiền thuê', 'Khác'];
const COSO = [
  { ten: 'AEON',    phanLoaiLon: 'Funzone', donVi: 'KVC' },
  { ten: 'VP HCM',  phanLoaiLon: 'Văn phòng', donVi: 'VP' },
];

/* ── Bệ đỡ: cây DOM đủ nhỏ để hàm vẽ thật dựng ra rồi hàm lưu thật đọc lại ───────────────
   Không dùng thư viện ngoài. Thay hàm vẽ bằng bản giả là bỏ mất đúng chỗ cần soi (hai đầu
   của dây chuyền có khớp nhau không). */
function doTag(tag) {
  return a => { const x = tag.match(new RegExp(a + '="([^"]*)"')); return x ? x[1] : null; };
}
/* Một thẻ <select>: giá trị = <option ... selected>, không có thì option đầu. */
function oChon(tag) {
  const m = tag.match(/<option value="([^"]*)"[^>]*selected/)
    || tag.match(/<option[^>]*selected[^>]*>([^<]*)</);
  if (m) return { value: m[1], disabled: false, setAttribute: function () {} };
  const d = tag.match(/<option value="([^"]*)"/);
  return { value: d ? d[1] : '', disabled: false, setAttribute: function () {} };
}
/* Bóc BẢNG TRÊN thành danh sách tbody, mỗi tbody là một đối tượng hàm lưu đi qua được. */
function docBangTren(h) {
  const ra = [];
  const re = /<tbody class="cfgMxBody" data-dau-muc="([^"]*)">([\s\S]*?)<\/tbody>/g;
  let m;
  while ((m = re.exec(h))) {
    const dm = m[1].replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&lt;/g, '<');
    const rows = [];
    m[2].split('<tr').slice(1).forEach(function (phan) {
      const than = phan.slice(0, phan.indexOf('</tr>') + 5);
      const dauTr = phan.slice(0, phan.indexOf('>'));
      const thuoc = {};
      (dauTr.match(/[a-z-]+="[^"]*"/g) || []).forEach(function (kv) {
        const i = kv.indexOf('='); thuoc[kv.slice(0, i)] = kv.slice(i + 2, -1);
      });
      const tags = than.match(/<input[^>]*>|<select[\s\S]*?<\/select>/g) || [];
      const o = { _thuoc: thuoc, style: {}, title: '', ten: null, khoi: null, loai: null,
                  misa: null, tkco: null, dauMuc: null, cha: null, vai: [], _oHet: [] };
      const vungVai = (than.match(/<div data-vai[\s\S]*?<\/div>\s*<\/div>/)
        || than.match(/<div data-vai[\s\S]*?<\/div>/) || [''])[0];
      tags.forEach(function (tag) {
        const at = doTag(tag);
        const f = { value: at('value') || '', checked: /\schecked/.test(tag), disabled: false,
                    getAttribute: at, setAttribute: function (k, v) { this['_' + k] = v; } };
        o._oHet.push(f);
        if (tag.slice(0, 6) === '<input') {
          if (at('data-goc') !== null) o.ten = f;
          else if (at('data-o') === 'misa') o.misa = f;
          else if (at('data-o') === 'tkco') o.tkco = f;
          else if (at('data-o') === 'cha') o.cha = f;
          else if (at('type') === 'checkbox' && vungVai.indexOf(tag) >= 0) o.vai.push(f);
        } else if (/<select[^>]*data-khoi-o/.test(tag)) { o.khoi = oChon(tag); o._oHet.push(o.khoi); }
        else if (at('data-o') === 'loaiTt') { o.loai = oChon(tag); o._oHet.push(o.loai); }
        else if (/<select[^>]*data-o="dauMuc"/.test(tag)) { o.dauMuc = oChon(tag); o._oHet.push(o.dauMuc); }
      });
      if (!o.ten) return;
      o.getAttribute = function (k) { return Object.prototype.hasOwnProperty.call(thuoc, k) ? thuoc[k] : null; };
      o.setAttribute = function (k, v) { thuoc[k] = v; };
      o.querySelector = function (sel) {
        if (sel.indexOf('data-goc') >= 0) return o.ten;
        if (sel.indexOf('data-khoi-o') >= 0) return o.khoi;
        if (sel.indexOf('loaiTt') >= 0) return o.loai;
        if (sel.indexOf('misa') >= 0) return o.misa;
        if (sel.indexOf('tkco') >= 0) return o.tkco;
        if (sel.indexOf('dauMuc') >= 0) return o.dauMuc;
        if (sel.indexOf('"cha"') >= 0) return o.cha;
        return null;
      };
      o.querySelectorAll = function (sel) {
        if (sel.indexOf('[data-vai]') >= 0 && sel.indexOf(':checked') >= 0) return o.vai.filter(function (c) { return c.checked; });
        if (/^input,select,button$/.test(sel)) return o._oHet;
        return [];
      };
      rows.push(o);
    });
    ra.push({ _dm: dm, _rows: rows,
      getAttribute: function (k) { return k === 'data-dau-muc' ? dm : null; },
      getElementsByTagName: function () { return rows; },
      querySelectorAll: function () { return []; } });
  }
  return ra;
}

function dungBe(ban, khoiXem) {
  const HTML = fs.readFileSync(path.join(GOC, 'wordpress', ban, 'templates/app.html'), 'utf8');
  const boc = function (ten) {
    const i = HTML.indexOf('function ' + ten + '(');
    t(ban + ': bốc được ' + ten + '()', i >= 0);
    return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
  };
  const bocBien = function (ten) {
    const i = HTML.indexOf('  var ' + ten + '=');
    return i < 0 ? '[]' : HTML.slice(HTML.indexOf('=', i) + 1, HTML.indexOf('];', i) + 1);
  };
  const bocDong = function (ten) {
    const i = HTML.indexOf('  var ' + ten + '=');
    return i < 0 ? '{}' : HTML.slice(HTML.indexOf('=', i) + 1, HTML.indexOf('\n', i)).replace(/;\s*$/, '');
  };
  const KHO = {};
  const NK = { luu: null, bodies: [], toast: [] };
  const moi = {
    CFG: { loaiChiPhi: JSON.parse(JSON.stringify(LOAI)), tkNoMatrix: [], coso: COSO, mangTk: [] },
    MX_LOCK: true, MX_SAP: 'mang', TKNAME: {}, TKCHART: [],
    KHOI_DS: [{ ma: 'kvc', ten: 'Khu vui chơi' }, { ma: 'mtd', ten: 'Máy tự động' }, { ma: 'vp', ten: 'Văn phòng' }],
    KHOI_MO: ['kvc', 'mtd', 'vp'], KHOI_DANG: 'kvc',
    VAI_GOC: ['Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên'],
    CURUSER: { role: 'Admin' },
    BOOT: { donVi: ['K&H', 'POSH'], xemDonVi: null, khoiBan: 'kvc', khoiXem: khoiXem || null,
            dauMucDs: DAU_MUC_DS },
    mxDatSap: function () {}, toggleMxLock: function () {},
    toast: function (k, m) { NK.toast.push([k, m]); },
    el: function (id) { return (KHO[id] = KHO[id] || { innerHTML: '', style: {}, textContent: '', className: '',
      getElementsByTagName: function () { return []; },
      querySelectorAll: function () { return []; }, querySelector: function () { return null; } }); },
    esc: function (x) { return String(x == null ? '' : x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },
    _delBtn: function () { return '<td></td>'; },
    _dvChuan: function (v) { return String(v == null ? '' : v).trim() || 'K&H'; },
    _saveCfg: function (p) { NK.luu = p; },
    document: { querySelectorAll: function (sel) {
      if (/tbody\.cfgMxBody/.test(sel)) return NK.bodies;
      return [];
    } },
    NK: NK, KHO: KHO,
  };
  moi.window = moi;
  const F = new Function('moi', 'with(moi){'
    + [boc('_inp'), boc('_loaiSel'), boc('_bpTach'), boc('_bpSelNhieu')].join('\n')
    + '\nvar KHOI_THEO_TEN_VAI=' + bocBien('KHOI_THEO_TEN_VAI') + ';'
    + '\nvar KHOI_DV_DUP=' + bocDong('KHOI_DV_DUP') + ';'
    + '\n' + ['_khoiDvBang', '_khoiCuaDv', '_tenKhoi', '_boDauVai', '_khoiCuaVai', '_vaiOKhoi',
      '_vaiConCua', '_vaiSelNhieu', '_khoiCuaLoai', '_mxBodies', '_khoiMo',
      '_khoiLuuTru', '_khoiBay', '_khoiDuoc', '_khoiSelLoai', '_dvSelNhieu', '_loaiChoDv',
      '_mangTong', '_mangTongDoan', '_mxMaGoc', '_mxSapCols', '_mxCols', '_mxNhomDv',
      '_xemDuocDv', '_dauMucSel', '_mxRowHtml', 'renderTkNoMatrix', '_khoaDongKhoiLa',
      'addCfgLoai', 'saveCfgTkNoMx'].map(boc).join('\n')
    + '\nreturn { ve: renderTkNoMatrix, luu: saveCfgTkNoMx, khoa: _khoaDongKhoiLa, them: addCfgLoai }; }')(moi);
  return { moi: moi, NK: NK, KHO: KHO, F: F, HTML: HTML };
}

/* Vẽ → bóc DOM → (sửa) → khoá → lưu. Đúng thứ tự mà trình duyệt chạy. */
function veRoiLuu(ban, khoiXem, sua) {
  const b = dungBe(ban, khoiXem);
  b.F.ve();
  const h = b.KHO.cfgTkNoMx.innerHTML;
  b.NK.bodies = docBangTren(h);
  b.F.khoa();                       // `renderTkNoMatrix()` gọi nó ngay sau `innerHTML=`
  if (sua) sua(b.NK.bodies, b);
  b.F.luu();
  return { h: h, r: b.NK.luu, b: b };
}

BAN.forEach(function (ban) {
  const f = path.join(GOC, 'wordpress', ban, 'templates/app.html');
  if (!fs.existsSync(f)) { t('có ' + ban + '/app.html', false); return; }

  /* ═══ 1. VẼ: MỘT BẢNG CHO MỖI ĐẦU MỤC ═════════════════════════════════════════════ */
  const v = veRoiLuu(ban, null, null);
  const h = v.h;
  const dmCoTren = (h.match(/data-dau-muc="([^"]*)"/g) || []).map(function (x) { return x.slice(14, -1); });
  teq('🔴 ' + ban + ': một bảng cho MỖI đầu mục, cộng ô hứng "Chưa xếp" đứng CUỐI',
    ['Chi phí chung', 'Chi phí cơ sở', 'Chi phí tiền thuê', 'Khác', ''], dmCoTren);
  t('   ô hứng có nhãn đọc được, không phải bảng không tên',
    h.indexOf('Chưa xếp đầu mục') >= 0, '');
  /* 🔴 KHÔNG ĐƯỢC CÒN DẤU VẾT CỦA TRỤC CŨ. Nửa đường dây nói đầu mục, nửa kia nói khối là
     đúng cái bẫy đã cắn ba lần tuần này. */
  t('🔴 ' + ban + ': tbody KHÔNG còn mốc `data-khoi` (trục cũ)', !/<tbody class="cfgMxBody" data-khoi=/.test(h), '');

  /* Mỗi loại nằm đúng bảng đầu mục của nó — và KHÔNG nằm ở bảng nào khác. */
  const bodies = docBangTren(h);
  const oBang = {};
  bodies.forEach(function (bd) { oBang[bd._dm] = bd._rows.map(function (r) { return r.ten.value; }); });
  teq('🔴 ' + ban + ': loại xếp đúng bảng đầu mục của nó',
    ['Chi phí NVL đồ ăn', 'Chi phí cơ sở'], oBang['Chi phí cơ sở']);
  teq('   loại chưa xếp rơi vào ô hứng, KHÔNG biến mất', ['Chi phí lặt vặt'], oBang['']);
  /* 🔴 ĐỐI CHỨNG SỐNG cho luật *"tránh dùng chung"*: hai "Chi phí khác" khác khối, cùng đầu
     mục — cả hai phải còn, không con nào nuốt con nào. */
  teq('🔴 ' + ban + ': hai loại TRÙNG TÊN khác khối đều sống trong cùng một bảng đầu mục',
    ['Chi phí khác', 'Chi phí khác'], oBang['Khác']);
  teq('   và tổng số dòng bày ra đúng bằng số loại trong sổ',
    LOAI.length, bodies.reduce(function (n, bd) { return n + bd._rows.length; }, 0));

  /* Khối vẫn đi theo TỪNG DÒNG — đó là thứ bảng mã TK Nợ cắt theo. */
  const khoiTren = {};
  bodies.forEach(function (bd) { bd._rows.forEach(function (r) {
    khoiTren[bd._dm + '/' + r.ten.value] = (r.khoi || {}).value; }); });
  teq('🔴 ' + ban + ': mỗi dòng vẫn mang KHỐI của nó trên ô chọn ngay trên dòng',
    { 'Chi phí cơ sở/Chi phí NVL đồ ăn': 'kvc', 'Chi phí chung/Chi phí chung VP': 'vp',
      'Chi phí tiền thuê/Thuê mall': 'mtd', '/Chi phí lặt vặt': 'kvc' },
    { 'Chi phí cơ sở/Chi phí NVL đồ ăn': khoiTren['Chi phí cơ sở/Chi phí NVL đồ ăn'],
      'Chi phí chung/Chi phí chung VP': khoiTren['Chi phí chung/Chi phí chung VP'],
      'Chi phí tiền thuê/Thuê mall': khoiTren['Chi phí tiền thuê/Thuê mall'],
      '/Chi phí lặt vặt': khoiTren['/Chi phí lặt vặt'] });
  teq('   và mang cả KHỐI LÚC VẼ trên chính thẻ hàng (`data-khoi-goc`)', 'vp',
    bodies.filter(function (bd) { return bd._dm === 'Chi phí chung'; })[0]._rows[0].getAttribute('data-khoi-goc'));

  /* ═══ 2. 🔴 VẼ RỒI LƯU LẠI: KHÔNG DÒNG NÀO MẤT KHỐI ═══════════════════════════════
   * Đây là phép quan trọng nhất của bài. Trước bản này khối đi nhờ mốc `data-khoi` của tbody;
   * mốc ấy nay là đầu mục, nên bỏ sót một chỗ là cột `khoi` nhận TÊN ĐẦU MỤC. */
  const r = v.r;
  t(ban + ': lượt Lưu có gọi `_saveCfg`', !!r, r);
  const sau = {}; (r.loaiChiPhi || []).forEach(function (x) { sau[x.khoi + '|' + x.ten] = x; });
  teq('🔴 ' + ban + ': lưu xong danh mục còn ĐỦ ' + LOAI.length + ' loại', LOAI.length, (r.loaiChiPhi || []).length);
  teq('🔴 ' + ban + ': KHỐI của mọi dòng sống sót qua một lượt lưu',
    LOAI.map(function (x) { return x.khoi; }).sort(),
    (r.loaiChiPhi || []).map(function (x) { return x.khoi; }).sort());
  /* 🔴 PHÉP BẮT ĐÚNG CÁI LỖI SỢ NHẤT: tên đầu mục lọt vào cột khối. */
  t('🔴 ' + ban + ': KHÔNG dòng nào bị đóng TÊN ĐẦU MỤC vào cột `khoi`',
    (r.loaiChiPhi || []).every(function (x) { return DAU_MUC_DS.indexOf(x.khoi) < 0; }),
    (r.loaiChiPhi || []).map(function (x) { return [x.ten, x.khoi]; }));
  t('   và mọi khối ghi xuống đều là mã khối thật',
    (r.loaiChiPhi || []).every(function (x) { return ['kvc', 'mtd', 'vp'].indexOf(x.khoi) >= 0; }),
    (r.loaiChiPhi || []).map(function (x) { return x.khoi; }));
  teq('🔴 ' + ban + ': ĐẦU MỤC của mọi dòng cũng sống sót',
    LOAI.map(function (x) { return x.dauMuc; }).sort(),
    (r.loaiChiPhi || []).map(function (x) { return x.dauMuc || ''; }).sort());
  /* 🔴 MẤY CỘT KHÔNG CÓ Ô NÀO TRÊN MÀN — chúng bay đầu tiên khi lượt Lưu dò nhầm bản cũ. */
  teq('🔴 ' + ban + ': TK Nợ / mã đối tượng / bộ phận (không có ô trên màn) không bay',
    { tkNo: '6412', maDt: 'DT1', boPhan: 'Cơ sở', donVi: 'K&H' },
    { tkNo: sau['kvc|Chi phí NVL đồ ăn'].tkNo, maDt: sau['kvc|Chi phí NVL đồ ăn'].maDt,
      boPhan: sau['kvc|Chi phí NVL đồ ăn'].boPhan, donVi: sau['kvc|Chi phí NVL đồ ăn'].donVi });
  teq('   kể cả dòng TRÙNG TÊN ở khối khác — mỗi bên giữ mã của mình',
    ['6419', '6429'], [sau['kvc|Chi phí khác'].tkNo, sau['vp|Chi phí khác'].tkNo]);
  teq('   và ô có mặt trên màn thì đọc màn (TK đối ứng, Tên MISA)',
    { tkCo: '331', tenMisa: 'NVL đồ ăn' },
    { tkCo: sau['kvc|Chi phí NVL đồ ăn'].tkCo, tenMisa: sau['kvc|Chi phí NVL đồ ăn'].tenMisa });

  /* ═══ 3. ĐỔI Ô ĐẦU MỤC = DÒNG NHẢY BẢNG, KHÔNG MẤT GÌ ═════════════════════════════
   * Đó là cách DUY NHẤT chuyển một loại sang đầu mục khác, nên nó phải chạy. */
  {
    const x = veRoiLuu(ban, null, function (bodies) {
      bodies.forEach(function (bd) { bd._rows.forEach(function (row) {
        if (row.ten.value === 'Thuê mall') row.dauMuc.value = 'Chi phí chung';
      }); });
    });
    const d = (x.r.loaiChiPhi || []).filter(function (y) { return y.ten === 'Thuê mall'; })[0] || {};
    teq('🔴 ' + ban + ': đổi ô Đầu mục → ghi xuống đầu mục MỚI', 'Chi phí chung', d.dauMuc);
    teq('   mà KHỐI của nó không đổi theo', 'mtd', d.khoi);
    teq('   và mã tài khoản của nó không bay', '6417', d.tkNo);
  }

  /* ═══ 4. ĐỔI Ô KHỐI = DÒNG SANG KHỐI KHÁC, MÃ CŨ VẪN THEO ═════════════════════════ */
  {
    const x = veRoiLuu(ban, null, function (bodies) {
      bodies.forEach(function (bd) { bd._rows.forEach(function (row) {
        if (row.ten.value === 'Chi phí lặt vặt') row.khoi.value = 'vp';
      }); });
    });
    const d = (x.r.loaiChiPhi || []).filter(function (y) { return y.ten === 'Chi phí lặt vặt'; })[0] || {};
    teq('🔴 ' + ban + ': đổi ô Khối → ghi xuống khối MỚI', 'vp', d.khoi);
    /* 🔴 `data-khoi-goc` sinh ra đúng cho ca này: không có nó thì lượt Lưu dò bản cũ bằng khối
       MỚI, không thấy gì, và mã tài khoản của dòng bay sạch — im lặng. */
    teq('   và mã tài khoản cũ vẫn đi theo (dò bản cũ bằng `data-khoi-goc`)', '6418', d.tkNo);
    teq('   đầu mục vẫn là ô hứng như cũ', '', d.dauMuc || '');
  }

  /* ═══ 4b. HÀNG KHÔNG CÓ Ô KHỐI THÌ GIỮ KHỐI CŨ, KHÔNG LẤY MỐC CỦA BẢNG ════════════
   * Hàng do bản cũ vẽ ra (hoặc một bản sau này bỏ cột Khối) không có `select[data-khoi-o]`.
   * 🔴 Lúc ấy KHÔNG được ngã về mốc của tbody: mốc ấy nay là TÊN ĐẦU MỤC, nên ngã về nó là
   *    ghi "Chi phí cơ sở" vào cột `khoi` — dòng rơi khỏi bảng mã TK Nợ, im lặng. Đường ngã
   *    đúng là `data-khoi-goc` (khối lúc vẽ). */
  {
    const x = veRoiLuu(ban, null, function (bodies) {
      bodies.forEach(function (bd) { bd._rows.forEach(function (row) {
        if (row.ten.value === 'Chi phí NVL đồ ăn') row.khoi = null;   // giả bộ hàng cũ, không có ô Khối
      }); });
    });
    const d = (x.r.loaiChiPhi || []).filter(function (y) { return y.ten === 'Chi phí NVL đồ ăn'; })[0] || {};
    teq('🔴 ' + ban + ': hàng không có ô Khối vẫn giữ đúng khối cũ', 'kvc', d.khoi);
    t('   và KHÔNG nhận tên đầu mục vào cột khối', d.khoi !== 'Chi phí cơ sở', d.khoi);
    teq('   mã tài khoản của nó cũng không bay', '6412', d.tkNo);
  }

  /* ═══ 4c. 🔴 NÚT "＋ THÊM LOẠI": CHẠY THẬT, KHÔNG SOI CHỮ ═════════════════════════
   * Nút này nay nhận ĐẦU MỤC, không nhận mã khối. Hai thứ phải đúng cùng lúc:
   *   · dòng mới rơi vào ĐÚNG bảng đầu mục người ta bấm,
   *   · và mang KHỐI CỦA NGƯỜI KHAI — không phải của cái bảng (bảng nay chẳng nói gì về
   *     khối). Mang khối họ không sửa được là dòng vừa thêm tự khoá ngay lượt vẽ sau, trông
   *     như bấm xong mất dòng. */
  {
    const b = dungBe(ban, ['kvc']);
    b.F.ve();
    const nut = [];
    /* Thân bảng giả, đủ những gì `addCfgLoai()` sờ tới. */
    const lamBody = function (dm) {
      const d = { tagName: 'DETAILS', open: false };
      return { _dm: dm, _them: '',
        getAttribute: function (k) { return k === 'data-dau-muc' ? dm : null; },
        parentNode: { tagName: 'DIV', parentNode: d }, _details: d,
        insertAdjacentHTML: function (vt, html) { this._them += html; },
        querySelectorAll: function () { return []; },
        getElementsByTagName: function () { return []; } };
    };
    ['Chi phí tiền thuê', ''].forEach(function (dm) { nut.push(lamBody(dm)); });
    b.NK.bodies = nut;
    b.moi.MX_LOCK = false;
    b.F.them('Chi phí tiền thuê');
    const moiHtml = nut[0]._them;
    t('🔴 ' + ban + ': bấm ＋ Thêm loại → dòng mới rơi vào ĐÚNG bảng đầu mục ấy',
      moiHtml.length > 0 && nut[1]._them === '', [moiHtml.length, nut[1]._them.length]);
    /* 🔴 Khối của dòng mới = khối của NGƯỜI KHAI ('kvc'), không phải tên bảng. */
    t('🔴 ' + ban + ': dòng mới mang KHỐI của người khai, không mang tên bảng',
      /data-khoi-goc="kvc"/.test(moiHtml), (moiHtml.match(/data-khoi-goc="[^"]*"/) || ['(không có)'])[0]);
    t('   và ô chọn Khối trên dòng cũng chọn sẵn khối ấy',
      /<option value="kvc" selected>/.test(moiHtml), (moiHtml.match(/<select data-khoi-o[\s\S]*?<\/select>/) || [''])[0].slice(0, 300));
    t('🔴 ' + ban + ': và ô Đầu mục chọn sẵn ĐÚNG đầu mục của bảng',
      />Chi phí tiền thuê<\/option>/.test(moiHtml.replace(/<option/g, '\n<option').split('\n')
        .filter(function (x) { return / selected/.test(x); }).join('')),
      moiHtml.replace(/<option/g, '\n<option').split('\n').filter(function (x) { return / selected/.test(x); }));
    /* Bấm ＋ ở ô hứng thì dòng mới cũng CHƯA xếp đầu mục, không tự nhét vào đầu mục nào. */
    b.F.them('');
    t('   bấm ＋ ở ô hứng → dòng mới cũng chưa xếp đầu mục',
      nut[1]._them.length > 0 && /<option value="">— chưa xếp —<\/option>/.test(nut[1]._them), '');
    /* 🔴 ĐỐI CHỨNG SỐNG cho "khối của NGƯỜI KHAI": kế toán Văn phòng, trên bản mà khối nhà
       là 'kvc'. Nếu dòng mới lấy khối theo bản chạy (`_khoiCuaLoai({})`) thì nó ra 'kvc' —
       tức dòng vừa thêm thuộc khối họ KHÔNG sửa được, và lượt vẽ sau tự khoá nó lại: bấm ＋
       xong thấy dòng xám ngắt, gõ không được, mà chẳng có câu nào giải thích.
       ⚠️ Không có ca này thì phép trên vô nghĩa — 'kvc' của người khai và 'kvc' của bản chạy
          trùng nhau nên đúng sai gì cũng ra một kết quả. */
    {
      const b3 = dungBe(ban, ['vp']);
      b3.moi.KHOI_DANG = 'kvc';           // thanh khối đang đứng ở khối họ không thuộc
      b3.F.ve();
      const nut3 = [lamBody('Khác')];
      b3.NK.bodies = nut3;
      b3.moi.MX_LOCK = false;
      b3.F.them('Khác');
      t('🔴 ' + ban + ': kế toán Văn phòng bấm ＋ → dòng mới mang khối VP, không mang khối của bản chạy',
        /data-khoi-goc="vp"/.test(nut3[0]._them),
        (nut3[0]._them.match(/data-khoi-goc="[^"]*"/) || ['(không có)'])[0]);
      t('   và ô chọn Khối trên dòng cũng chọn sẵn VP',
        /<option value="vp" selected>/.test(nut3[0]._them),
        (nut3[0]._them.match(/<select data-khoi-o[\s\S]*?<\/select>/) || [''])[0].slice(0, 300));
    }
    /* 🔴 CHỐI NGƯỜI CHƯA THUỘC KHỐI NÀO — nút không vẽ cho họ, nhưng gọi thẳng từ thanh
       địa chỉ là qua được. */
    const b2 = dungBe(ban, []);
    b2.moi.BOOT.khoiXem = ['khong-co-khoi-nao'];
    b2.F.ve();
    const nut2 = [lamBody('Khác')];
    b2.NK.bodies = nut2;
    b2.moi.MX_LOCK = false;
    b2.F.them('Khác');
    t('🔴 ' + ban + ': người chưa thuộc khối nào gọi thẳng `addCfgLoai()` thì bị chối',
      nut2[0]._them === '' && b2.NK.toast.some(function (x) { return x[0] === 'warn'; }), b2.NK.toast);
  }

  /* ═══ 5. 🔴 DÒNG CỦA KHỐI NGƯỜI NÀY KHÔNG SỬA ĐƯỢC ════════════════════════════════
   * Kế toán chỉ xem được Khu vui chơi. Bảng đầu mục bày lẫn dòng của VP và MTĐ — khoá phải
   * theo TỪNG DÒNG, và lượt Lưu không được tin mỗi cái khoá ấy. */
  {
    /* ⚠️ `BOOT.khoiXem` là MÃ KHỐI viết thường ('kvc'), không phải tên đơn vị ('KVC') — xem
       `_khoiDuoc()`. Gieo sai là không khối nào khớp, `_khoiDuoc()` trả cả ba, và MỌI phép của
       mục này hoá XANH GIẢ: không dòng nào bị khoá thì chẳng có gì để canh. */
    const b = dungBe(ban, ['kvc']);
    b.F.ve();
    const hk = b.KHO.cfgTkNoMx.innerHTML;
    b.NK.bodies = docBangTren(hk);
    b.F.khoa();
    const het = []; b.NK.bodies.forEach(function (bd) { bd._rows.forEach(function (row) { het.push(row); }); });
    const la = het.filter(function (row) { return row.getAttribute('data-khoi-la'); })
                  .map(function (row) { return row.ten.value; }).sort();
    teq('🔴 ' + ban + ': khoá ĐÚNG mấy dòng của khối người này không sửa được',
      ['Chi phí chung VP', 'Chi phí khác', 'Thuê mall'], la);
    t('   và KHÔNG khoá nhầm dòng của chính họ',
      het.filter(function (row) { return row.ten.value === 'Chi phí NVL đồ ăn'; })[0].getAttribute('data-khoi-la') === null, '');
    t('🔴 ' + ban + ': ô của dòng bị khoá thật sự tắt', het.filter(function (row) {
      return row.getAttribute('data-khoi-la'); }).every(function (row) {
      return row.ten.disabled === true; }), '');
    /* 🔴 CỔNG THẬT NẰM Ở LƯỢT LƯU. Giả bộ người ta gỡ `disabled` rồi gõ đè — bằng công cụ lập
       trình của trình duyệt là làm được, nên màn hình không phải chỗ chốt. */
    het.forEach(function (row) {
      if (!row.getAttribute('data-khoi-la')) return;
      row.ten.value = row.ten.value + ' (BỊ SỬA TRỘM)';
      if (row.khoi) row.khoi.value = 'kvc';
      if (row.tkco) row.tkco.value = '999';
    });
    b.F.luu();
    const r2 = b.NK.luu || {};
    const conVp = (r2.loaiChiPhi || []).filter(function (x) { return x.khoi === 'vp' || x.khoi === 'mtd'; });
    teq('🔴 ' + ban + ': gõ trộm lên dòng khối lạ KHÔNG ăn — chép nguyên bản cũ', 3, conVp.length);
    t('   tên không đổi', conVp.every(function (x) { return x.ten.indexOf('BỊ SỬA TRỘM') < 0; }),
      conVp.map(function (x) { return x.ten; }));
    t('   khối không bị kéo về khối của kẻ sửa',
      conVp.every(function (x) { return x.khoi === 'vp' || x.khoi === 'mtd'; }), conVp.map(function (x) { return x.khoi; }));
    t('   TK đối ứng cũng vậy', conVp.every(function (x) { return x.tkCo !== '999'; }),
      conVp.map(function (x) { return x.tkCo; }));
    teq('   và danh mục vẫn còn đủ, không nuốt dòng nào', LOAI.length, (r2.loaiChiPhi || []).length);
  }
});

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: mỗi đầu mục một bảng, và vẽ rồi lưu lại không dòng nào mất khối.');
