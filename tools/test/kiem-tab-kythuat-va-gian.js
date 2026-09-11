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

/* ── 5. 🔴 GỘP HAI LỐI THÀNH MỘT: CHỌN LOẠI RỒI TỰ VÀO ĐÚNG CHỖ ─────────────────────────
   Anh Thắng: *"Chọn chi phí tuần, mà hiện bảng chi phí dự án"* → *"nên anh mới cần gộp nó lại
   thành 1, chọn xong tự hỏi ra đơn gì tránh lộn"*.

   Cặp nút "Chi phí · cơ sở / Dự án · gian thi công" trông như bộ chọn LOẠI ĐƠN nhưng chỉ đổi
   TRANG. Bỏ cặp ấy đi thì phải lo hai chuyện: hộp "đơn này là gì" ở đâu cũng đủ lựa chọn, và
   chọn xong tự sang đúng trang + mở đúng nhánh. */
function beGop(quyen) {
  const NK = { trang: [], nhom: [], toast: [], dong: 0, tuan: 0 };
  const KHO = {};
  const moi = {
    QUYEN_TAB: quyen,
    el: id => (KHO[id] = KHO[id] || { _id: id, style: { display: '' }, value: '',
      focus() { NK.focus = id; }, scrollIntoView() {} }),
    showPage: p => NK.trang.push(p),
    closeNewDon: () => { NK.dong++; },
    toast: (k, m) => NK.toast.push(m),
    /* 🔴 GHI LẠI THAM SỐ. `daMoTao(nhomSan)` nhận loại đã chọn ở hộp trước; quên truyền là khối
       tạo hỏi lại "Đơn này là gì?" lần thứ hai — đúng thứ anh Thắng gặp. Bệ đỡ chỉ ghi
       `moTao = true` thì đục chỗ truyền tham số vẫn xanh. */
    daMoTao: n => { NK.moTao = true; NK.moTaoNhom = n; },
    daChonNhom: n => NK.nhom.push(n),
    daDongTao: () => { NK.dongTao = true; },
    newDon: () => { NK.tuan++; },
    /* Hàm thật gọi setTimeout rồi mới chuyển — chạy thẳng để bài kiểm khỏi phải chờ. */
    setTimeout: fn => fn(),
  };
  const src = `${boc('_tabDuoc')}\n${boc('_vaoDuocDuAn')}\n${boc('ndChonLoai')}\n${boc('daSangDonTuan')}
    return { chon: ndChonLoai, sangTuan: daSangDonTuan, vaoDuAn: _vaoDuocDuAn };`;
  const R = new Function('moi', `with(moi){ ${src} }`)(moi);
  R.NK = NK; R.el = moi.el;
  return R;
}

const CA2 = beGop({ don: 1, duan: 1 });
t('🔴 hỏi loại đơn hay không nay tra BẢNG QUYỀN, không dò nút trong thanh vừa bỏ',
  CA2.vaoDuAn() === true, CA2.vaoDuAn());
const CHIDON = beGop({ don: 1, duan: 0 });
t('   không vào được tab Kỹ thuật thì không hỏi', CHIDON.vaoDuAn() === false, CHIDON.vaoDuAn());

const GX1 = beGop({ don: 1, duan: 1 });
GX1.chon('dacoso');
t('🔴 chọn "Chi phí cơ sở · Kỹ thuật": đóng hộp, sang tab Kỹ thuật, mở đúng nhánh cơ sở',
  GX1.NK.dong === 1 && GX1.NK.trang.join(',') === 'duan' && GX1.NK.moTao === true
  && GX1.NK.moTaoNhom === 'coso', GX1.NK);
t('🔴 loại đi CÙNG lời gọi mở khối tạo — không thì khối ấy hỏi lại lần thứ hai',
  GX1.NK.moTaoNhom === 'coso' && GX1.NK.nhom.length === 0, GX1.NK);
t('   và đặt con trỏ vào ô TUẦN', GX1.NK.focus === 'daTuan', GX1.NK.focus);
t('   câu nhắc nói đúng việc phải làm tiếp (chọn tuần)', /TUẦN/i.test(GX1.NK.toast.join('|')), GX1.NK.toast);

const GX2 = beGop({ don: 1, duan: 1 });
GX2.chon('duan');
t('🔴 chọn "Chi phí dự án": mở nhánh dự án, con trỏ vào ô TÊN GIAN',
  GX2.NK.moTaoNhom === 'duan' && GX2.NK.focus === 'daTen', GX2.NK);
t('   hai nhánh Kỹ thuật KHÔNG tự đẻ ra đơn — đơn cần tên hoặc tuần, tạo bừa là phải xoá đi làm lại',
  GX2.NK.tuan === 0 && GX1.NK.tuan === 0, [GX1.NK.tuan, GX2.NK.tuan]);

const GX3 = beGop({ don: 1, duan: 1 });
GX3.chon('coso');
t('   chọn "Đơn tuần của cơ sở": ở lại hộp tạo đơn tuần, KHÔNG chuyển trang',
  GX3.NK.trang.length === 0 && GX3.NK.dong === 0, GX3.NK);

const GX4 = beGop({ don: 1, duan: 1 });
GX4.sangTuan();
t('🔴 từ khối tạo của tab Kỹ thuật chọn "Đơn tuần của cơ sở": sang tab đơn và MỞ THẲNG hộp tạo',
  GX4.NK.dongTao === true && GX4.NK.trang.join(',') === 'don' && GX4.NK.tuan === 1, GX4.NK);

/* Nút chuyển một chiều: chạy THẬT hàm vẽ nó, với một trang giả có đủ hai nút. */
function beNutChuyen(vis) {
  const nut = [{ di: 'don', style: { display: 'x' } }, { di: 'duan', style: { display: 'x' } }];
  const moi = {
    document: { querySelectorAll: sel => (sel === '[data-dcsw-di]' ? nut : []) },
    Array: Array,
  };
  new Function('moi', 'V', `with(moi){ ${boc('_veNutChuyenDon')}
    nut.forEach(function(b){ b.getAttribute=function(){ return b.di; }; });
    _veNutChuyenDon(V); }`).call(null, Object.assign(moi, { nut }), vis);
  return { don: nut[0].style.display, duan: nut[1].style.display };
}
const NC = beNutChuyen({ don: 1, duan: 0 });
t('🔴 chỉ vào được đơn tuần: hiện nút sang đơn tuần, ẨN nút sang Kỹ thuật',
  NC.don === '' && NC.duan === 'none', NC);
const NC2 = beNutChuyen({ don: 0, duan: 1 });
t('   ngược lại cũng vậy', NC2.don === 'none' && NC2.duan === '', NC2);
const NC3 = beNutChuyen(null);
t('   chưa có bảng quyền thì ẩn cả hai, không nổ', NC3.don === 'none' && NC3.duan === 'none', NC3);

/* Cặp nút LOẠI ĐƠN cũ phải biến mất khỏi trang — còn sót là còn chỗ để lộn. */
t('🔴 không còn cặp nút bật/tắt "LOẠI ĐƠN" trong trang', HTML.indexOf('data-dcsw=') < 0);
t('   thay bằng nút chuyển một chiều, mặc định ẩn',
  (HTML.match(/data-dcsw-di="/g) || []).length === 2, (HTML.match(/data-dcsw-di="/g) || []).length);
/* 🔴 CANH CẢ DẤU NHÁY ĐÓNG. Dò `indexOf('ndLoaiDaCoSo')` thì một id dài hơn ("ndLoaiDaCoSoXyz")
   vẫn khớp vì nó là TIỀN TỐ — đục id đi mà phép vẫn xanh. */
t('   và hộp "Đơn này là loại nào?" có đủ ba lối',
  HTML.indexOf('id="ndLoaiCoSo"') >= 0 && HTML.indexOf('id="ndLoaiDaCoSo"') >= 0
  && HTML.indexOf('id="ndLoaiDuAn"') >= 0);

/* ═══════════════════════════════════════════════════════════════════════════════════════ */
console.log('');
if (TRUOT.length) {
  console.log('❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(x => console.log('   • ' + x));
  process.exit(1);
}
console.log('✅ ĐẠT ' + DAT + ' / ' + DAT + ' — tab Kỹ thuật + ô Gian xổ danh sách cơ sở');
process.exit(0);
