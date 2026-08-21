/**
 * Kiểm HÀNG ĐỢI GHI SONG SONG (wordpress/vhcp-cham-cong/apps-script/ghi-song-song.gs)
 *
 * Tệp .gs đó chạy trong Apps Script, không chạy được ở đây — nên bài kiểm dựng lại đúng mấy đối
 * tượng nó dùng (SpreadsheetApp, UrlFetchApp, LockService, PropertiesService) rồi CHẠY THẬT hàm
 * của nó. Đọc mã bằng regex thì không bắt được lỗi luồng, mà luồng ở đây là chấm công.
 *
 * Điều phải canh gắt nhất: `wpXepHang` nằm trên ĐƯỜNG NÓNG của `doPost`. Nó ném lỗi là lượt chấm
 * công đó không vào sheet — mất công thật, để đổi lấy một bản sao chưa ai dùng.
 *
 *   node tools/test/test-ghi-song-song.js
 */
const fs = require('fs');
const path = require('path');

const FILE = path.join(__dirname, '..', '..', 'wordpress', 'vhcp-cham-cong', 'apps-script', 'ghi-song-song.gs');
const src = fs.readFileSync(FILE, 'utf8');

let dat = 0; const truot = [];
function t(ten, dk, them) {
  if (dk) { dat++; return; }
  truot.push(ten + (them === undefined ? '' : `\n      → ${typeof them === 'string' ? them : JSON.stringify(them)}`));
}
function teq(ten, mong, nhan) {
  t(`${ten} (mong ${JSON.stringify(mong)})`, JSON.stringify(mong) === JSON.stringify(nhan), nhan);
}

/** Sheet giả: chỉ đủ những phương thức tệp .gs thật sự gọi. */
function SheetGia(ten) {
  this.ten = ten; this.o = [];              // o[hang][cot], 0-based
  this.frozen = 0;
}
SheetGia.prototype.getLastRow = function () { return this.o.length; };
SheetGia.prototype.setFrozenRows = function (n) { this.frozen = n; return this; };
SheetGia.prototype.appendRow = function (r) {
  /* Giới hạn THẬT của Google Sheets: một ô tối đa 50.000 ký tự. Đây chính là lý do hàng đợi phải
     cắt ảnh — và bài kiểm phải mô phỏng giới hạn này, không thì phép thử xanh mà thật thì nổ. */
  r.forEach((v) => { if (typeof v === 'string' && v.length > 50000) { throw new Error('Ô quá 50000 ký tự'); } });
  this.o.push(r.slice());
  return this;
};
SheetGia.prototype.deleteRow = function (h) { this.o.splice(h - 1, 1); };
SheetGia.prototype.getRange = function (h, c, sh, sc) {
  const s = this; sh = sh || 1; sc = sc || 1;
  return {
    getValues() {
      const ra = [];
      for (let i = 0; i < sh; i++) {
        const d = [];
        for (let j = 0; j < sc; j++) { const r = s.o[h - 1 + i] || []; d.push(r[c - 1 + j] === undefined ? '' : r[c - 1 + j]); }
        ra.push(d);
      }
      return ra;
    },
    setValues(v) {
      for (let i = 0; i < v.length; i++) {
        while (s.o.length < h - 1 + i + 1) s.o.push([]);
        for (let j = 0; j < v[i].length; j++) s.o[h - 1 + i][c - 1 + j] = v[i][j];
      }
      return this;
    },
    setFontWeight() { return this; },
  };
};

function dungMoiTruong(opt) {
  opt = opt || {};
  const G = {};
  const sheets = {};
  G.SpreadsheetApp = {
    getActiveSpreadsheet: () => ({
      getSheetByName: (n) => sheets[n] || null,
      insertSheet: (n) => { sheets[n] = new SheetGia(n); return sheets[n]; },
    }),
  };
  G._sheets = sheets;

  const props = Object.assign({}, opt.props);
  G.PropertiesService = {
    getScriptProperties: () => ({
      getProperty: (k) => (k in props ? props[k] : null),
      setProperty: (k, v) => { props[k] = v; },
    }),
  };
  G._props = props;

  G.daGui = [];
  G.UrlFetchApp = {
    fetch: (url, o) => {
      G.daGui.push({ url, o });
      const r = opt.dap ? opt.dap(url, o, G.daGui.length) : { ma: 200, tho: '{"status":"SUCCESS"}' };
      if (r.nem) throw new Error(r.nem);
      return { getResponseCode: () => r.ma, getContentText: () => r.tho };
    },
  };
  G.LockService = { getScriptLock: () => ({ tryLock: () => opt.khoaTruot !== true, releaseLock() {} }) };
  G.triggers = [];
  G.ScriptApp = {
    newTrigger: (fn) => ({ timeBased: () => ({ everyMinutes: () => ({ create: () => { G.triggers.push(fn); } }) }) }),
    getProjectTriggers: () => G.triggers.map((f) => ({ getHandlerFunction: () => f })),
    deleteTrigger: (tr) => { const i = G.triggers.indexOf(tr.getHandlerFunction()); if (i >= 0) G.triggers.splice(i, 1); },
  };
  G.Logger = { log() {} };

  const ten = Object.keys(G);
  const f = new Function(...ten, src + '\n;return {wpXepHang, wpDayHangDoi, wpBatDongBo, wpTatDongBo, wpTinhTrang, wpDoiSoHang, _wpSheet_};');
  return Object.assign({ api: f(...ten.map((k) => G[k])) }, G);
}

/* Đọc một dòng hàng đợi mà KHÔNG làm cả bài kiểm sập khi hàng đợi chưa được tạo. Bỏ cắt ảnh là
   `wpXepHang` bỏ luôn việc xếp hàng -> không có sheet -> phiên bản trước của bài kiểm này CHẾT
   bằng TypeError, tức không đọc được chỗ nào sai lẫn còn bao nhiêu phép thử chưa chạy.
   Trượt phải ra báo trượt, không ra sập. */
function dong(e, i) {
  const q = e._sheets.DongBoWP;
  return q && q.o[i] ? q.o[i] : null;
}
function than(e, i) {
  const d = dong(e, i);
  if (!d) return null;
  try { return JSON.parse(String(d[1] || '')); } catch (err) { return null; }
}

const CF = { props: { WP_URL: 'https://x.test/cham-cong-may', WP_KEY: 'k' } };
function goi(o) {
  return JSON.stringify(Object.assign({
    macAddress: 'AA:01', hikSerial: 'SN1', hikModel: 'M', stationName: 'TUTU_BT',
    employeeNo: 'NV001', name: 'A', time: '2026-08-20 08:00:00', image: '',
  }, o));
}

// ============================================================ 1. Xếp hàng
{
  const e = dungMoiTruong(CF);
  t('xếp hàng: lượt hợp lệ vào hàng đợi', e.api.wpXepHang(goi({})) === true);
  const q = e._sheets.DongBoWP;
  t('hàng đợi có dòng tiêu đề + 1 dòng dữ liệu', q && q.getLastRow() === 2, q && q.getLastRow());
  const d = than(e, 1) || {};
  teq('giữ nguyên mã NV', 'NV001', d.employeeNo);
  teq('giữ nguyên thời điểm máy gửi', '2026-08-20 08:00:00', d.time);
  teq('trạng thái ban đầu là CHỜ (rỗng)', '', dong(e, 1) ? dong(e, 1)[2] : null);
  teq('số lần thử ban đầu là 0', 0, dong(e, 1) ? dong(e, 1)[3] : null);
}
{
  /* Gói thử đường truyền: firmware đẩy một gói mỗi lần bật máy, trên MỌI máy. Xếp vào là hàng
     đợi đầy rác cho thứ cả hai bên đều bỏ. */
  const e = dungMoiTruong(CF);
  t('gói TEST4G KHÔNG xếp hàng', e.api.wpXepHang(goi({ employeeNo: 'TEST4G', time: 'test' })) === false);
  t('cờ selftest cũng KHÔNG xếp hàng', e.api.wpXepHang(goi({ selftest: true })) === false);
  t('không tạo dòng nào', !e._sheets.DongBoWP || e._sheets.DongBoWP.getLastRow() <= 1);
}
{
  /* Ngược lại: giờ SAI KHUÔN vẫn phải xếp hàng. Lọc bớt ở bên này là tự tay xoá mất chỗ lệch cần
     tìm — mục đích của việc ghi song song là hai bên nhận CÙNG đầu vào rồi mới so kết quả. */
  const e = dungMoiTruong(CF);
  t('giờ sai khuôn VẪN xếp hàng (để đối chiếu được cách hai bên xử)',
    e.api.wpXepHang(goi({ time: '20/08/2026 8h' })) === true);
}
{
  /* ⚠️ Chỗ này là lý do hàng đợi phải cắt ảnh: ô sheet tối đa 50.000 ký tự, ảnh mặt base64 của
     JPEG 100 KB là ~133.000. Không cắt là appendRow NÉM LỖI ngay trên đường nóng của doPost. */
  const e = dungMoiTruong(CF);
  const anh = 'A'.repeat(133000);
  t('gói kèm ảnh 133.000 ký tự vẫn xếp hàng được (đã cắt ảnh)', e.api.wpXepHang(goi({ image: anh })) === true);
  const d = than(e, 1) || {};
  teq('trường image đã bị cắt rỗng', '', d.image);
  teq('nhưng CÓ giữ cờ để biết lượt đó có ảnh bên Drive', true, d.anhCo);
  const raw = dong(e, 1);
  t('dòng trong hàng đợi ngắn hơn giới hạn ô sheet', !!raw && String(raw[1]).length < 50000,
    raw ? String(raw[1]).length : 'không có dòng nào — ảnh chưa bị cắt?');
}
{
  const e = dungMoiTruong(CF);
  e.api.wpXepHang(goi({ image: '' }));
  teq('gói 4G không ảnh -> anhCo = false', false, (than(e, 1) || {}).anhCo);
}
// ---- Đường nóng: KHÔNG BAO GIỜ được ném lỗi lên doPost ----
{
  const e = dungMoiTruong(CF);
  let nem = false;
  [undefined, null, '', 'khong-phai-json{{{', '[]', '123'].forEach((x) => {
    try { e.api.wpXepHang(x); } catch (err) { nem = true; }
  });
  t('đầu vào rác/rỗng: KHÔNG ném lỗi lên doPost', !nem);
}
{
  // Sheet chết hẳn (hết quota, mất quyền) cũng không được làm doPost chết.
  const e = dungMoiTruong(CF);
  e.SpreadsheetApp.getActiveSpreadsheet = () => { throw new Error('sheet chết'); };
  let nem = false;
  try { e.api.wpXepHang(goi({})); } catch (err) { nem = true; }
  t('sheet chết: KHÔNG ném lỗi lên doPost', !nem);
}
{
  // Mã NV dài bất thường -> bỏ xếp hàng, KHÔNG để appendRow nổ trên đường nóng.
  const e = dungMoiTruong(CF);
  let nem = false; let ra;
  try { ra = e.api.wpXepHang(goi({ name: 'B'.repeat(60000) })); } catch (err) { nem = true; }
  t('gói quá dài sau khi cắt ảnh: không ném lỗi, chỉ bỏ xếp hàng', !nem && ra === false);
  t('và có ghi vết để đọc ra, không im lặng', String(e._props.wp_vet || '').indexOf('QUA_DAI') >= 0);
}

// ============================================================ 2. Đẩy hàng đợi
{
  const e = dungMoiTruong(CF);
  e.api.wpXepHang(goi({ employeeNo: 'NV001' }));
  e.api.wpXepHang(goi({ employeeNo: 'NV002' }));
  e.api.wpDayHangDoi();
  teq('đẩy đủ 2 lượt', 2, e.daGui.length);
  teq('gửi bằng POST', 'post', e.daGui[0].o.method);
  teq('gửi kèm khoá ở tiêu đề', 'k', e.daGui[0].o.headers['X-VHCC-Key']);
  teq('gửi đúng địa chỉ đã khai', 'https://x.test/cham-cong-may', e.daGui[0].url);
  /* followRedirects PHẢI là false: firmware không đi theo chuyển hướng, nên nếu bên này lặng lẽ
     đi theo thì suốt giai đoạn ghi song song trông êm, tới lúc trỏ firmware về mới vỡ — trên máy
     thật, mất công thật. */
  teq('KHÔNG đi theo chuyển hướng (để lỗi địa chỉ lộ ra ngay)', false, e.daGui[0].o.followRedirects);
  const q = e._sheets.DongBoWP;
  teq('cả hai dòng thành xong', ['xong', 'xong'], [q.o[1][2], q.o[2][2]]);
  e.api.wpDayHangDoi();
  teq('chạy lại: KHÔNG gửi lại dòng đã xong', 2, e.daGui.length);
}
{
  /* Cùng luật firmware: 200 + có chữ SUCCESS. Nới lỏng là hai bên hiểu "xong" khác nhau. */
  const e = dungMoiTruong(Object.assign({}, CF, { dap: () => ({ ma: 200, tho: '{"status":"ERROR"}' }) }));
  e.api.wpXepHang(goi({}));
  e.api.wpDayHangDoi();
  teq('HTTP 200 mà thân KHÔNG có SUCCESS -> chưa xong, còn chờ', '', e._sheets.DongBoWP.o[1][2]);
  teq('đếm số lần thử', 1, e._sheets.DongBoWP.o[1][3]);
}
{
  const e = dungMoiTruong(Object.assign({}, CF, { dap: () => ({ ma: 500, tho: 'lỗi máy chủ' }) }));
  e.api.wpXepHang(goi({}));
  for (let i = 0; i < 4; i++) e.api.wpDayHangDoi();
  teq('thử 4 lần vẫn trượt -> vẫn còn chờ (chưa tới ngưỡng 5)', '', e._sheets.DongBoWP.o[1][2]);
  e.api.wpDayHangDoi();
  teq('thử lần thứ 5 -> TREO, thôi đẩy', 'treo', e._sheets.DongBoWP.o[1][2]);
  const g = e.daGui.length;
  e.api.wpDayHangDoi();
  teq('dòng treo KHÔNG bị đẩy lại nữa', g, e.daGui.length);
  /* Dòng treo KHÔNG được xoá: nó là bằng chứng của một lượt CÓ trong sheet mà KHÔNG có trong
     MySQL. Xoá là lúc đối số hàng hai bên khớp GIẢ. */
  t('dòng treo vẫn nằm trong hàng đợi làm bằng chứng', e._sheets.DongBoWP.getLastRow() === 2);
  t('và có ghi vết TREO', String(e._props.wp_vet || '').indexOf('TREO') >= 0);
}
{
  // Chuyển hướng: phải nói rõ là SAI ĐỊA CHỈ, chứ không chỉ "HTTP 301".
  const e = dungMoiTruong(Object.assign({}, CF, { dap: () => ({ ma: 301, tho: '' }) }));
  e.api.wpXepHang(goi({}));
  e.api.wpDayHangDoi();
  const note = String(e._sheets.DongBoWP.o[1][5] || '');
  t('gặp chuyển hướng: kết quả nói rõ WP_URL sai và vì sao nguy',
    note.indexOf('CHUYỂN HƯỚNG') >= 0 && note.indexOf('WP_URL') >= 0, note);
}
{
  // UrlFetchApp ném lỗi (mất mạng) -> không được làm lịch chết, dòng phải còn để thử lại.
  const e = dungMoiTruong(Object.assign({}, CF, { dap: () => ({ nem: 'DNS chết' }) }));
  e.api.wpXepHang(goi({}));
  let nem = false;
  try { e.api.wpDayHangDoi(); } catch (err) { nem = true; }
  t('mất mạng: lịch không chết', !nem);
  teq('dòng vẫn còn chờ để thử lại', '', e._sheets.DongBoWP.o[1][2]);
}
{
  // Chưa khai WP_URL / WP_KEY -> không gửi đi đâu cả, và ghi vết.
  const e = dungMoiTruong({});
  e.api.wpXepHang(goi({}));
  e.api.wpDayHangDoi();
  teq('chưa khai cấu hình: không gửi lượt nào', 0, e.daGui.length);
  t('và ghi vết CHUA_KHAI', String(e._props.wp_vet || '').indexOf('CHUA_KHAI') >= 0);
}
{
  // Lượt lịch trước còn chạy -> bỏ lượt này, không gửi trùng.
  const e = dungMoiTruong(Object.assign({}, CF, { khoaTruot: true }));
  e.api.wpXepHang(goi({}));
  e.api.wpDayHangDoi();
  teq('không lấy được khoá: không gửi (tránh gửi trùng)', 0, e.daGui.length);
}
{
  // Mỗi lượt lịch chỉ đẩy một lô, không cố đẩy hết rồi hết thời gian chạy.
  const e = dungMoiTruong(CF);
  for (let i = 0; i < 25; i++) e.api.wpXepHang(goi({ employeeNo: 'NV' + i }));
  e.api.wpDayHangDoi();
  teq('mỗi lượt lịch đẩy tối đa một lô 20', 20, e.daGui.length);
  e.api.wpDayHangDoi();
  teq('lượt sau đẩy nốt 5 dòng còn lại', 25, e.daGui.length);
}

{
  /* DỌN HÀNG ĐỢI. Dòng `xong` quá WP_GIU_NGAY ngày thì dọn được; dòng `treo` thì KHÔNG BAO GIỜ —
     nó là bằng chứng của một lượt CÓ trong sheet mà KHÔNG có trong MySQL. Dọn nó là lúc đối số
     hàng hai bên khớp GIẢ, đúng loại sai làm cả việc ghi song song thành vô nghĩa.
     Phải làm CŨ dòng đi mới thử được: phiên bản trước của bài kiểm này dùng dòng vừa tạo, mà dòng
     mới thì chưa tới hạn dọn nên phép thử xanh kể cả khi luật dọn bị phá. */
  const e = dungMoiTruong(Object.assign({}, CF, { dap: (u, o, n) => (n === 1 ? { ma: 200, tho: 'SUCCESS' } : { ma: 500, tho: 'x' }) }));
  e.api.wpXepHang(goi({ employeeNo: 'XONG' }));
  e.api.wpXepHang(goi({ employeeNo: 'TREO' }));
  for (let i = 0; i < 5; i++) e.api.wpDayHangDoi();
  const cu = new Date(Date.now() - 30 * 86400000);
  e._sheets.DongBoWP.o[1][0] = cu;
  e._sheets.DongBoWP.o[2][0] = cu;                       // cả hai đều đã 30 ngày
  teq('trước khi dọn: 1 xong + 1 treo', ['xong', 'treo'], [dong(e, 1)[2], dong(e, 2)[2]]);
  e.api.wpDayHangDoi();                                   // lượt này gọi _wpDon_
  const con = [];
  for (let i = 1; i <= 2; i++) { const r = dong(e, i); if (r) con.push(String(r[2])); }
  teq('dọn xong dòng XONG cũ, GIỮ dòng TREO làm bằng chứng', ['treo'], con);
}

// ============================================================ 3. Lịch và tình trạng
{
  const e = dungMoiTruong(CF);
  e.api.wpBatDongBo();
  teq('bật đồng bộ: tạo đúng 1 lịch', ['wpDayHangDoi'], e.triggers);
  e.api.wpBatDongBo();
  teq('bật hai lần KHÔNG tạo lịch trùng', 1, e.triggers.length);
  e.api.wpTatDongBo();
  teq('tắt đồng bộ: hết lịch', 0, e.triggers.length);
}
{
  const e = dungMoiTruong(Object.assign({}, CF, { dap: (u, o, n) => (n === 1 ? { ma: 200, tho: 'SUCCESS' } : { ma: 500, tho: 'x' }) }));
  e.api.wpXepHang(goi({ employeeNo: 'A' }));
  e.api.wpXepHang(goi({ employeeNo: 'B' }));
  e.api.wpDayHangDoi();
  const tt = e.api.wpTinhTrang();
  teq('tình trạng: 1 xong, 1 còn chờ', { cho: 1, xong: 1, treo: 0 }, tt.hangDoi);
  teq('tình trạng: báo đã khai địa chỉ', true, tt.daKhaiUrl);
  teq('tình trạng: báo đã khai khoá', true, tt.daKhaiKey);
}
{
  // Đối số hàng phải CẢNH BÁO khi còn dòng treo — đừng để ai chuyển firmware lúc đang lệch.
  const e = dungMoiTruong(Object.assign({}, CF, { dap: () => ({ ma: 500, tho: 'x' }) }));
  e.api.wpXepHang(goi({ time: '2026-08-20 08:00:00' }));
  for (let i = 0; i < 5; i++) e.api.wpDayHangDoi();
  const kq = e.api.wpDoiSoHang('TUTU_BT', '2026-08');
  teq('đối số hàng: đếm được dòng treo', 1, kq.hangDoi.treo);
  t('đối số hàng: nói rõ ĐỪNG chuyển firmware khi còn dòng treo',
    kq.ghiChu.indexOf('Đừng chuyển firmware') >= 0, kq.ghiChu);
}
{
  const e = dungMoiTruong(CF);
  e.api.wpXepHang(goi({ time: '2026-08-20 08:00:00' }));
  e.api.wpXepHang(goi({ time: '2026-07-15 08:00:00' }));
  e.api.wpDayHangDoi();
  const kq = e.api.wpDoiSoHang('TUTU_BT', '2026-08');
  teq('đối số hàng chỉ đếm tháng được hỏi', 1, kq.hangDoi.xong);
  t('hàng đợi sạch thì nói rõ là sạch', kq.ghiChu.indexOf('sạch') >= 0, kq.ghiChu);
}

// ============================================================ 4. Không có bí mật trong tệp
t('tệp .gs KHÔNG ghi cứng địa chỉ WordPress thật', !/https:\/\/khmatrix\.com\/cham/.test(src.replace(/\* {6}WP_URL[^\n]*/g, '')) || src.indexOf('WP_URL  =') > 0);
t('tệp .gs KHÔNG chứa mã triển khai Apps Script', src.indexOf('AKfycb') < 0);
t('tệp .gs KHÔNG chứa địa chỉ Firebase', !/firebaseio|default-rtdb/.test(src));
t('tệp .gs đọc địa chỉ + khoá từ Script Property, không ghi cứng',
  src.indexOf("getProperty('WP_URL')") > 0 && src.indexOf("getProperty('WP_KEY')") > 0);
/* Tệp này TUYỆT ĐỐI không được khai doPost: Apps Script chỉ nhận MỘT doPost, khai cái thứ hai là
   cửa của cả chuỗi máy chấm công chết im lặng. */
t('tệp .gs KHÔNG khai doPost (Apps Script chỉ nhận một)', !/function\s+doPost\s*\(/.test(src));

if (truot.length) {
  console.error(`HỎNG: ${truot.length}`);
  truot.forEach((x) => console.error('  ✗ ' + x));
  console.error(`ĐẠT: ${dat}`);
  process.exit(1);
}
console.log(`ĐẠT: ${dat} phép thử — hàng đợi ghi song song không chạm đường nóng của doPost.`);
