/**
 * MỞ ĐƠN — phản hồi về KHÔNG theo thứ tự gửi đi thì không được đè lên đơn đang xem.
 *
 * VÌ SAO CÓ TỆP NÀY
 * 24/08/2026 anh Thắng báo "đơn cũ bị nhảy đè vô": đang xem đơn tuần 24/8 mà bảng dòng hiện
 * ra là của tuần 17/8. Nguyên nhân không phải dữ liệu lẫn — create_don sinh đơn rỗng, get_don
 * lọc đúng mã. Nguyên nhân là openDon() gọi máy chủ bất đồng bộ mà không đánh dấu lượt: hỏi
 * đơn A rồi hỏi đơn B, nếu A về sau thì nó ghi đè CUR và vẽ lại bằng dòng của A.
 *
 * Lấy thẳng hàm openDon trong app.html ra chạy — chép lại luật ở đây là rồi sẽ lệch với app
 * mà phép thử vẫn xanh.
 *
 *   node tools/test/test-mo-don.js
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const GOC = path.join(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');

let dat = 0; const hong = [];
function t(ten, dieu, nhan) { if (dieu) { dat++; return; } hong.push(ten + (nhan === undefined ? '' : ' → nhận được: ' + JSON.stringify(nhan))); }
function teq(ten, mong, nhan) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(nhan), nhan); }

function layHam(ten) {
  const m = HTML.match(new RegExp('\\n  function ' + ten + '\\([\\s\\S]*?\\n  \\}'));
  if (!m) { console.error('HỎNG: không tìm thấy hàm ' + ten + ' trong app.html'); process.exit(1); }
  return m[0];
}
// _openSeq khai ngoài hàm, phải lấy kèm
function layBien(ten) {
  const m = HTML.match(new RegExp('\\n  var ' + ten + '\\s*=\\s*[^;]*;'));
  if (!m) { console.error('HỎNG: không tìm thấy biến ' + ten + ' trong app.html'); process.exit(1); }
  return m[0];
}

// ---- sân giả: đủ để openDon chạy, mọi thứ khác thành hàm rỗng ----
function dungSan() {
  const oGia = () => ({ style: {}, classList: { add() {}, remove() {} }, innerHTML: '', textContent: '', value: '', onclick: null });
  const cho = [];          // hàng đợi lời gọi máy chủ chưa trả lời
  const daVe = [];         // các đơn đã thật sự vẽ ra màn hình
  const loi = [];          // thông báo lỗi hiện ra

  const chay = {
    withSuccessHandler(f) { this._ok = f; return this; },
    withFailureHandler(f) { this._er = f; return this; },
    getDon(ma) { cho.push({ ma, ok: this._ok, er: this._er }); },
  };

  const san = {
    el: oGia,
    loading() {},
    toast(k, m) { if (k === 'err') loi.push(String(m)); },
    google: { script: { run: {
      withSuccessHandler(f) { return Object.assign(Object.create(chay), { _ok: f }); },
    } } },
    CUR: null,
    CURUSER: { role: 'Admin' },
    canDo: () => true,
    renderDonList() {}, _syncDonView() {}, renderTU() {}, renderRecon() {}, renderQT() {},
    setProducts() {}, resetLineForm() {}, _khoaCoSo() {}, _viSaoKhongGui: () => ({ html: '', mau: '' }),
    _canDelDon: () => true, money: (x) => String(x), esc: (x) => String(x),
    renderLines() { daVe.push(san.CUR && san.CUR.don ? String(san.CUR.don.maDon) : null); },
    console,
  };
  /* openDon chạm tới hàng chục hàm phụ (stCls, _viSaoKhongGui, _mondayOf…). Khai tay từng cái
     là danh sách sẽ mục nát mỗi lần app đổi. Bọc bằng Proxy: cái nào chưa khai thì trả một hàm
     rỗng — phép thử này chỉ quan tâm THỨ TỰ phản hồi, không quan tâm màn hình vẽ ra sao. */
  const boc = new Proxy(san, {
    /* has() trả true cho MỌI tên thì mọi tra cứu biến đều đi qua đây — kể cả String, Number,
       JSON. Trả stub cho chúng là hỏng ngầm: String(x) ra chuỗi rỗng, phép so sánh mã đơn
       luôn bằng nhau, và phép thử xanh trong khi mã thật sai. Nên phải để toàn cục chuẩn đi qua. */
    has(o, k) { return !(k in globalThis) || (k in o); },
    get(o, k) {
      if (k in o) return o[k];
      if (k in globalThis) return globalThis[k];
      if (typeof k === 'symbol') return undefined;
      return function () { return ''; };
    },
    set(o, k, v) { o[k] = v; return true; },
  });
  vm.createContext(boc);
  vm.runInContext(layBien('_openSeq') + '\n' + layHam('openDon'), boc);
  return { san: boc, cho, daVe, loi };
}

function traLoi(muc, ma, lines) {
  muc.ok({ success: true, don: { maDon: ma, trangThai: 'Nháp', ghiChu: '', duPhong: 0, buTru: 0 }, lines: lines || [], products: [] });
}

// ---------------------------------------------------------------- 1. lượt cũ về SAU
{
  const { san, cho, daVe } = dungSan();
  san.openDon('D-CU');
  san.openDon('D-MOI');
  teq('gửi đi 2 lượt hỏi', 2, cho.length);
  traLoi(cho[1], 'D-MOI');           // đơn mới về trước
  traLoi(cho[0], 'D-CU');            // đơn CŨ về sau — không được đè
  teq('chỉ vẽ đơn mới, đơn cũ về muộn bị bỏ', ['D-MOI'], daVe);
  teq('CUR giữ đơn đang xem', 'D-MOI', san.CUR.don.maDon);
}

// ---------------------------------------------------------------- 2. đúng thứ tự thì vẫn chạy
{
  const { san, cho, daVe } = dungSan();
  san.openDon('D-A');
  traLoi(cho[0], 'D-A');
  teq('một lượt bình thường vẫn vẽ', ['D-A'], daVe);
}

// ---------------------------------------------------------------- 3. lỗi của lượt cũ không cướp màn hình
{
  const { san, cho, daVe, loi } = dungSan();
  san.openDon('D-CU');
  san.openDon('D-MOI');
  traLoi(cho[1], 'D-MOI');
  cho[0].er(new Error('mạng rớt'));   // lượt cũ lỗi, về sau
  teq('lỗi của lượt cũ không hiện ra', [], loi);
  teq('màn hình vẫn là đơn mới', 'D-MOI', san.CUR.don.maDon);
}

// ---------------------------------------------------------------- 4. máy chủ trả nhầm đơn thì phải nói ra
{
  const { san, cho, daVe, loi } = dungSan();
  san.openDon('D-HOI');
  traLoi(cho[0], 'D-KHAC');           // hỏi một đằng trả một nẻo
  teq('không vẽ đơn trả nhầm', [], daVe);
  t('báo lỗi trả nhầm đơn', loi.length === 1 && /D-KHAC/.test(loi[0]) && /D-HOI/.test(loi[0]), loi);
}

// ---------------------------------------------------------------- 5. bỏ chọn đơn thì phản hồi đang bay về không dựng lại
{
  const { san, cho, daVe } = dungSan();
  san.openDon('D-A');
  san.openDon('');                    // người dùng bỏ chọn
  traLoi(cho[0], 'D-A');              // phản hồi về sau khi đã đóng
  teq('đơn đã đóng không tự dựng lại', [], daVe);
  teq('CUR vẫn rỗng', null, san.CUR);
}

console.log('');
if (hong.length) { console.log('ĐẠT: ' + dat + ' phép thử'); console.log('HỎNG: ' + hong.length); hong.forEach((h) => console.log('  ✗ ' + h)); process.exit(1); }
console.log('ĐẠT: ' + dat + ' phép thử'); console.log('Mở đơn: phản hồi về sai thứ tự không đè được lên đơn đang xem.');
