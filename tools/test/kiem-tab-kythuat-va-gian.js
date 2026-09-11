/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * KỸ THUẬT KHÔNG LÊN ĐƠN TUẦN CỦA CƠ SỞ · Ô GIAN PHẢI XỔ RA DANH SÁCH CƠ SỞ.
 *
 * Anh Thắng 11/09/2026, chỉ vào màn "1) Nhập hạng mục xin tạm ứng" của đơn tuần:
 * *"này của nhân viên cơ sở, không phải của kỹ thuật, ẩn đi"*, rồi chỉ vào khối 🔧 Chi phí
 * Kỹ thuật: *"Đây mới chính là chi phí do kỹ thuật lên"*. Và: *"gian cơ sở không hiện lên"*.
 *
 * =============================================================================================
 * 🔴 ẨN TAB RỒI THÌ PHẢI ĐỔI CẢ TRANG MẶC ĐỊNH. Nhân viên đăng nhập xong vào thẳng tab đơn;
 *    ẩn tab ấy mà quên chỗ này là người Kỹ thuật mở app ra thấy một trang vừa bị ẩn, hàng tab
 *    không nút nào sáng, và họ tưởng app hỏng.
 *
 * 🔴 TAB GỘP "📋 Đơn chi phí" NHỚ LOẠI LẦN TRƯỚC BẰNG localStorage. Người Kỹ thuật từng đứng ở
 *    "Chi phí · cơ sở" sẽ bị đưa về đúng cái vừa ẩn — phải kẹp lại.
 *
 * 🔴 Ô GIAN LÀ <input> CÓ DANH SÁCH, KHÔNG PHẢI <select>. Gian mới chưa có trong danh mục thì
 *    vẫn phải gõ được, nếu không đơn Setup gian mới không nhập nổi dòng nào. Nhưng phải gợi ý,
 *    vì mã tài khoản tra theo MẢNG của gian: "Gian A" / "GIAN A" / "gian a" ra ba mã khác nhau.
 *
 * ⚠️ CHẠY THẬT hàm bốc từ app.html.
 *
 * Chạy: node tools/test/kiem-tab-kythuat-va-gian.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const boc = ten => {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
};
const bocVar = ten => {
  const i = HTML.indexOf('var ' + ten + '=');
  t('bốc được var ' + ten, i >= 0);
  if (i < 0) return '';
  return HTML.slice(i, HTML.indexOf(';', i) + 1);
};

/* ── 1. AI LÊN ĐƠN TUẦN CỦA CƠ SỞ ──────────────────────────────────────────────────────── */
const vaoDon = new Function('BP', `${bocVar('BP_KHONG_DON_COSO')}\n${boc('_vaoDonCoSo')}
  return _vaoDonCoSo(BP);`);
t('🔴 nhân viên Kỹ thuật KHÔNG lên đơn tuần của cơ sở', vaoDon('Kỹ thuật') === false, vaoDon('Kỹ thuật'));
t('   nhân viên Cơ sở thì có', vaoDon('Cơ sở') === true, vaoDon('Cơ sở'));
t('   nhân viên Văn phòng thì có', vaoDon('Văn phòng') === true, vaoDon('Văn phòng'));
t('🔴 chưa khai bộ phận thì vẫn lên được — chặn cả người chưa khai là chặn nhầm cả công ty',
  vaoDon('') === true && vaoDon(null) === true, [vaoDon(''), vaoDon(null)]);
t('   tên bộ phận thừa khoảng trắng vẫn nhận ra', vaoDon('  Kỹ thuật  ') === false, vaoDon('  Kỹ thuật  '));

/* ── 2. TRANG MẶC ĐỊNH VÀ TAB GỘP PHẢI THEO QUYỀN ─────────────────────────────────────── */
function beTab(quyen, nhoLanTruoc) {
  const NK = { trang: [] };
  const moi = {
    QUYEN_TAB: quyen, DONCHI_MODE: nhoLanTruoc || 'don',
    showPage: p => NK.trang.push(p),
  };
  const src = `${boc('_tabDuoc')}\n${boc('defaultPageFor')}\n${boc('showDonChi')}
    return { tabDuoc: _tabDuoc, macDinh: defaultPageFor, donChi: showDonChi };`;
  const R = new Function('moi', `with(moi){ ${src} }`)(moi);
  R.NK = NK;
  return R;
}
const KT = beTab({ tongquan: 1, don: 0, duan: 1 });
t('🔴 Kỹ thuật đăng nhập: vào thẳng tab Kỹ thuật, KHÔNG phải tab đơn vừa ẩn',
  KT.macDinh('Nhân viên') === 'duan', KT.macDinh('Nhân viên'));
const CSO = beTab({ tongquan: 1, don: 1, duan: 0 });
t('   nhân viên cơ sở vẫn vào thẳng tab đơn như cũ', CSO.macDinh('Nhân viên') === 'don', CSO.macDinh('Nhân viên'));
t('   kế toán / quản lý vẫn vào Tổng quan', CSO.macDinh('Kế toán cá nhân') === 'tongquan', CSO.macDinh('Kế toán cá nhân'));
const HET = beTab({ tongquan: 1, don: 0, duan: 0 });
t('   không vào được cả hai: ngã về Tổng quan chứ không trang trắng',
  HET.macDinh('Nhân viên') === 'tongquan', HET.macDinh('Nhân viên'));

const NHO = beTab({ tongquan: 1, don: 0, duan: 1 }, 'don');
NHO.donChi();
t('🔴 tab gộp nhớ "Chi phí · cơ sở" từ lần trước nhưng nay bị ẩn → mở sang tab Kỹ thuật',
  NHO.NK.trang.length === 1 && NHO.NK.trang[0] === 'duan', NHO.NK.trang);
const NHO2 = beTab({ tongquan: 1, don: 1, duan: 1 }, 'duan');
NHO2.donChi();
t('   loại nhớ lần trước còn xem được thì giữ nguyên', NHO2.NK.trang[0] === 'duan', NHO2.NK.trang);
const CHUA = beTab(null, 'don');
t('🔴 chưa có bảng quyền (gọi trước applyPerms) thì coi như được — không thì màn trắng trơn',
  CHUA.tabDuoc('don') === true && CHUA.macDinh('Nhân viên') === 'don', CHUA.macDinh('Nhân viên'));

/* ── 3. Ô GIAN XỔ RA DANH SÁCH CƠ SỞ ──────────────────────────────────────────────────── */
function beGian() {
  const KHO = {};
  const moi = {
    el: id => (KHO[id] = KHO[id] || { _id: id, innerHTML: '' }),
    esc: s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'),
  };
  const R = new Function('moi', `with(moi){ ${boc('_daNapCoSoDs')}\n return _daNapCoSoDs; }`)(moi);
  return { nap: R, el: moi.el };
}
const G = beGian();
G.nap(['ADV GO! AN LẠC', 'FUNZONE VŨNG TÀU']);
t('🔴 ô Gian của dòng chi được đổ danh sách cơ sở — đây là cái "không hiện lên"',
  /ADV GO! AN LẠC/.test(G.el('dl_gian_da').innerHTML) && /FUNZONE VŨNG TÀU/.test(G.el('dl_gian_da').innerHTML),
  G.el('dl_gian_da').innerHTML);
t('   ô tên dự án (Tháo dỡ) vẫn được đổ như cũ',
  /ADV GO! AN LẠC/.test(G.el('dl_coso_da').innerHTML), G.el('dl_coso_da').innerHTML);
t('   mỗi cơ sở một <option>', (G.el('dl_gian_da').innerHTML.match(/<option/g) || []).length === 2, G.el('dl_gian_da').innerHTML);
const G2 = beGian();
G2.nap(['Gian "A" <b>']);
t('🔴 tên cơ sở có dấu nháy / thẻ HTML bị rào — nếu không, một tên lạ làm hỏng cả ô gợi ý',
  !/<b>/.test(G2.el('dl_gian_da').innerHTML) && /&quot;A&quot;/.test(G2.el('dl_gian_da').innerHTML),
  G2.el('dl_gian_da').innerHTML);
const G3 = beGian();
G3.nap(null);
t('   danh sách rỗng: dọn sạch, không nổ', G3.el('dl_gian_da').innerHTML === '', G3.el('dl_gian_da').innerHTML);

/* 🔴 CANH CẶP: ô Gian phải trỏ ĐÚNG id của một <datalist> CÓ THẬT trong trang. Trỏ sai id thì
   trình duyệt im lặng không gợi ý gì — đúng triệu chứng anh Thắng gặp, và không có lỗi nào. */
const oGian = /<input id="da_gian"[^>]*>/.exec(HTML);
t('tìm thấy ô Gian trong trang', !!oGian);
const dsId = oGian ? (/(?:^|\s)list="([^"]+)"/.exec(oGian[0]) || [])[1] : '';
t('🔴 ô Gian khai thuộc tính list', !!dsId, oGian && oGian[0]);
t('🔴 và id ấy là một <datalist> CÓ THẬT trong trang',
  !!dsId && HTML.indexOf('<datalist id="' + dsId + '"') >= 0, dsId);
t('   đúng ô mà _daNapCoSoDs() đổ dữ liệu vào',
  !!dsId && HTML.indexOf("'" + dsId + "'") >= 0, dsId);

/* ── 4. 🔴 Ô LOẠI CHI PHÍ CỦA ĐƠN KỸ THUẬT PHẢI CÓ "Chi phí cơ sở" ───────────────────────
   Anh Thắng: *"bổ sung loại chi phí ( chi phí cơ sở )"*.

   Gốc: mã tài khoản tra theo MẢNG của gian, mà lúc mới mở form ô Gian còn trống — và nhánh
   "chưa chọn gian thì gom mã của MỌI mảng" lại gác sau cờ `_donNhieuCoSo()`. Đơn chi phí cơ sở
   của Kỹ thuật cũng là MỘT đơn nhiều gian nhưng không bật cờ ấy, nên mọi loại khai mã theo ma
   trận (chính là "Chi phí cơ sở") bị coi là chưa có mã và bị ẩn. */
function beLoai(opt) {
  opt = opt || {};
  const moi = {
    CUR: opt.CUR || null,
    CUR_PAGE: opt.page === undefined ? 'duan' : opt.page,
    DA_CUR: opt.DA_CUR === undefined ? { maDA: 'DA1', isCoSo: true } : opt.DA_CUR,
    CURUSER: { boPhan: opt.bp === undefined ? 'Kỹ thuật' : opt.bp },
    NHOM_CP: '',
    BOOT: {
      /* "Chi phí cơ sở" KHÔNG khai mã cố định và KHÔNG khai bộ phận — đúng hình dạng trong
         danh mục gốc. Mã của nó nằm ở ma trận theo mảng. */
      loaiChiPhi: [
        { ten: 'Chi phí cơ sở', tkNo: '', tkCo: '141', boPhan: '' },
        { ten: 'Chi phí tháo dỡ', tkNo: '', tkCo: '141', boPhan: 'Kỹ thuật' },
        { ten: 'Nguyễn Hữu Thọ, Nguyễn Bá Tuấn', tkNo: '', tkCo: '', boPhan: '' },
      ],
      tkNoMx: { 'chi phí cơ sở': { farm: ['64166'], fz: ['64126'] } },
      coso: ['Gian A', 'Gian B'],
      cosoPll: { 'gian a': 'FARM', 'gian b': 'FZ' },
    },
  };
  const src = `${boc('_mangCua')}\n${boc('_donNhieuCoSo')}\n${boc('_mangPham')}
    ${boc('_tkNoList')}\n${boc('_tkNoCua')}\n${boc('_bpTach')}\n${boc('_khoaNhom')}
    ${boc('_loaiCpList')}
    return { nhieu: _donNhieuCoSo, ds: _loaiCpList, tkList: _tkNoList };`;
  return new Function('moi', `with(moi){ ${src} }`)(moi);
}
const ten = L => L.map(x => x.ten);

const KTCS = beLoai({});
t('🔴 đơn chi phí cơ sở của Kỹ thuật được tính là ĐƠN NHIỀU GIAN', KTCS.nhieu() === true, KTCS.nhieu());
t('🔴 ô Gian còn TRỐNG mà ô Loại chi phí vẫn có "Chi phí cơ sở" — đây là cái đang thiếu',
  ten(KTCS.ds('', '')).indexOf('Chi phí cơ sở') >= 0, ten(KTCS.ds('', '')));
t('   và hai mã của nó (64166 FARM · 64126 FZ) đều tra ra được khi chưa chọn gian',
  KTCS.tkList('Chi phí cơ sở', '').length === 2, KTCS.tkList('Chi phí cơ sở', ''));
t('🔴 chọn gian rồi thì HẸP LẠI đúng mã của mảng gian ấy, không mượn mảng khác',
  KTCS.tkList('Chi phí cơ sở', 'Gian A').join(',') === '64166', KTCS.tkList('Chi phí cơ sở', 'Gian A'));
t('   gian thuộc mảng khác ra mã khác', KTCS.tkList('Chi phí cơ sở', 'Gian B').join(',') === '64126', KTCS.tkList('Chi phí cơ sở', 'Gian B'));
t('   loại của Kỹ thuật vẫn còn (đã khai bộ phận nên hiện dù chưa có mã)',
  ten(KTCS.ds('', '')).indexOf('Chi phí tháo dỡ') >= 0, ten(KTCS.ds('', '')));
t('🔴 dòng rác nạp từ sổ cũ vẫn bị ẩn — không khai mã, không khai bộ phận',
  ten(KTCS.ds('', '')).indexOf('Nguyễn Hữu Thọ, Nguyễn Bá Tuấn') < 0, ten(KTCS.ds('', '')));

/* Dự án Setup / Tháo dỡ KHÔNG phải đơn nhiều gian — gian chính là tên dự án. */
const KTDA = beLoai({ DA_CUR: { maDA: 'DA2', isCoSo: false } });
t('   dự án Setup/Tháo dỡ: không tính là đơn nhiều gian', KTDA.nhieu() === false, KTDA.nhieu());

/* ⚠️ `DA_CUR` sống suốt phiên: mở đơn cơ sở Kỹ thuật rồi quay về tab đơn tuần thì trang ấy
   KHÔNG được tưởng mình ghép nhiều gian — nếu không, ô Cơ sở của nó nhảy xuống cuối form. */
const QUAYVE = beLoai({ page: 'don', CUR: { don: { nhieuCoSo: false } } });
t('🔴 quay về tab đơn tuần: cờ nhiều-gian TẮT, dù DA_CUR còn treo từ lượt trước',
  QUAYVE.nhieu() === false, QUAYVE.nhieu());
const POSH = beLoai({ page: 'don', CUR: { don: { nhieuCoSo: true } }, DA_CUR: null });
t('   đơn tuần POSH vẫn là đơn nhiều cơ sở như cũ', POSH.nhieu() === true, POSH.nhieu());

/* ═══════════════════════════════════════════════════════════════════════════════════════ */
console.log('');
if (TRUOT.length) {
  console.log('❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(x => console.log('   • ' + x));
  process.exit(1);
}
console.log('✅ ĐẠT ' + DAT + ' / ' + DAT + ' — tab Kỹ thuật + ô Gian xổ danh sách cơ sở');
process.exit(0);
