/**
 * "BẤM THÊM DÒNG NHƯNG KHÔNG THẤY GÌ" — MỌI CỬA CHẶN PHẢI NÓI RA LÝ DO
 * =============================================================================================
 *
 * Anh Thắng 22/09/2026: *"Bấm đã thêm dòng nhưng không thấy gì"*.
 *
 * 🔴 HAI NGUYÊN NHÂN, CẢ HAI ĐỀU IM LẶNG THEO CÁCH RIÊNG:
 *
 *   1. CÂU BÁO ĐI QUA MÀ KHÔNG AI ĐỌC. Mọi cửa chặn đều có `toast()`, nhưng toast nổi ở góc
 *      màn rồi tự tắt — lúc bấm nút này người ta đang cuộn ở CUỐI form, mắt ở chỗ khác hẳn.
 *
 *   2. HÀM CHẾT GIỮA CHỪNG. Một lỗi ném ra (ô mất, hàm đổi tên, dữ liệu lạ) làm `saveLine()`
 *      dừng ngay tại đó: không dòng nào được thêm, không câu nào được nói. Người bấm chỉ thấy
 *      nút nhấp một cái rồi thôi — đúng chữ "không thấy gì".
 *
 * ⚠️ BÀI NÀY CHẠY THẬT `saveLine()` TRÊN DOM GIẢ, không soi chữ. Phép soi chữ xanh suốt cả hai
 *    ca trên: câu báo vẫn nằm trong mã, cửa chặn vẫn nằm trong mã. Chỉ khi GỌI mới thấy.
 *
 * Chạy: node tools/test/kiem-them-dong-bao-ly-do.js
 */
const fs = require('fs'), path = require('path');
const vm = require('vm');
const GOC = path.resolve(__dirname, '..', '..');
const h = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');

let dat = 0; const truot = [];
function t(ten, dk, them) {
  if (dk) { dat++; return; }
  truot.push(ten + (them === undefined ? '' : '\n      → ' + String(them).slice(0, 200)));
}
function than(ten) {
  const i = h.indexOf('function ' + ten + '(');
  if (i < 0) throw new Error('không thấy ' + ten + '()');
  let j = h.indexOf('{', i), d = 0;
  for (let k = j; k < h.length; k++) {
    if (h[k] === '{') d++;
    else if (h[k] === '}') { d--; if (!d) return h.slice(i, k + 1); }
  }
  throw new Error('ngoặc lệch ' + ten);
}

/** Dựng một "trang" tối thiểu: mỗi ô là một object có .value và .style. */
function moi(gia) {
  const O = {};
  const themO = (id, v) => { O[id] = { value: v == null ? '' : String(v), style: {}, focus() {} }; };
  /* ⚠️ DANH SÁCH Ô LẤY THẲNG TỪ MÃ NGUỒN, không kê tay. Kê tay thì quên một ô (như `f_ngay`
     lúc viết bài này) là bệ đỡ ném "Cannot read properties of null" — và bài đỏ vì BỆ ĐỠ
     thiếu, chứ không phải vì mã hỏng. Bốc từ chính `collectLine()` thì thêm ô mới ở app là
     bệ đỡ tự có theo. */
  const IDS = [...new Set([...(than('collectLine') + than('_saveLine'))
    .matchAll(/el\('([^']+)'\)/g)].map(m => m[1]))];
  IDS.forEach(id => themO(id, (gia || {})[id]));
  ['f_pltt', 'f_dt', 'lineId'].forEach(id => { if (!O[id]) themO(id, (gia || {})[id]); });
  O.lineLyDo = { textContent: '', style: {} };

  const goi = [];          // lời gọi máy chủ
  const noi = [];          // mọi câu nói ra cho người dùng
  const ctx = {
    el: id => O[id] || null,
    toast: (k, c) => noi.push(k + ':' + c),
    loading: () => {},
    _guardChi: () => false,
    _oTien: id => (O[id] ? O[id].value : ''),
    _dmy: x => x,
    money: x => String(x),
    _log: () => {},
    openDon: () => {},
    dongLineForm: () => {},
    /* 🔴 TRỤC PHÂN TÍCH (22/09/2026) — `collectLine()` nay gộp mọi trục qua `trucGui()`.
       Thiếu nó thì `_saveLine()` ném `trucGui is not defined` và MỌI phép của bài này đỏ vì
       bệ đỡ thiếu, chứ không phải vì mã hỏng. Trả một trục mẫu, không trả rỗng: rỗng thì phép
       "dòng gửi lên mang đủ ô" vẫn xanh dù khung đã gãy. */
    trucGui: () => ({ giaiDoan: 'Vận hành' }),
    /* 24/09/2026 — cơ sở chỉ bắt buộc khi PHÂN LOẠI LỚN đang chọn có cơ sở (`_dmCoSoCua`); bệ này
       chưa chọn đầu mục → '*' (như cũ, đòi cơ sở). Bốc hàm THẬT, kèm BOOT rỗng, để luật ấy chạy. */
    DM_CUR: '', BOOT: { dauMucCoSo: {} },
    CUR: { don: { maDon: 'D1' } },
    google: { script: { run: {
      withSuccessHandler() { return this; },
      withFailureHandler() { return this; },
      addLine(...a) { goi.push(['addLine', ...a]); },
      updateLine(...a) { goi.push(['updateLine', ...a]); },
    } } },
  };
  vm.createContext(ctx);
  vm.runInContext([than('_tn'), than('_dmCoSoCua'), than('collectLine'), than('_lyDoKhongThem'), than('saveLine'), than('_saveLine')].join('\n'), ctx);
  return { ctx, O, goi, noi };
}

/* ── 1. 🔴 TỪNG Ô CÒN THIẾU PHẢI RA MỘT CÂU ĐỌC ĐƯỢC, VÀ TÔ ĐỎ ĐÚNG Ô ẤY ──────────────────── */
const CA = [
  { ten: 'thiếu CƠ SỞ',      gia: {},                                                        o: 'f_coso', chu: /CƠ SỞ/ },
  { ten: 'thiếu LOẠI CHI PHÍ', gia: { f_coso: 'FARM' },                                      o: 'f_nhom', chu: /LOẠI CHI PHÍ/ },
  { ten: 'thiếu NỘI DUNG',   gia: { f_coso: 'FARM', f_nhom: 'Chi phí cơ sở' },               o: 'f_nd',   chu: /NỘI DUNG/ },
  { ten: 'thiếu SỐ LƯỢNG',   gia: { f_coso: 'FARM', f_nhom: 'Chi phí cơ sở', f_nd: 'Nước' }, o: 'f_sl',   chu: /SỐ LƯỢNG/ },
];
CA.forEach(function (c) {
  const m = moi(c.gia);
  m.ctx.saveLine();
  t('🔴 ' + c.ten + ' -> NÓI RA lý do, nằm lì dưới nút', c.chu.test(m.O.lineLyDo.textContent || ''),
    m.O.lineLyDo.textContent);
  t('   ' + c.ten + ' -> và tô đỏ ĐÚNG ô còn thiếu', m.O[c.o].style.borderColor === '#dc2626',
    c.o + ' = ' + m.O[c.o].style.borderColor);
  t('   ' + c.ten + ' -> KHÔNG gọi máy chủ', m.goi.length === 0, m.goi.length);
});

/* Số lượng 0 cũng phải chặn: thành tiền ra 0 mà nhìn bảng không thấy sai ở đâu. */
{
  const m = moi({ f_coso: 'FARM', f_nhom: 'CP', f_nd: 'Nước', f_sl: '0' });
  m.ctx.saveLine();
  t('🔴 số lượng 0 cũng bị chặn — thành tiền ra 0 mà bảng trông vẫn bình thường',
    /SỐ LƯỢNG/.test(m.O.lineLyDo.textContent || ''), m.O.lineLyDo.textContent);
}

/* ── 2. 🔴 ĐỦ Ô THÌ PHẢI THẬT SỰ GỌI MÁY CHỦ, và xoá câu báo cũ ───────────────────────────── */
{
  const m = moi({ f_coso: 'FARM', f_nhom: 'Chi phí cơ sở', f_nd: 'Nước bình', f_sl: '2', f_dg: '20000' });
  m.O.lineLyDo.textContent = 'câu báo của lượt trước';
  m.ctx.saveLine();
  t('🔴 đủ ô -> GỌI addLine, không im lặng cũng không chặn oan', m.goi.length === 1 && m.goi[0][0] === 'addLine', m.goi);
  t('   và XOÁ câu báo cũ đi — để lại là người ta tưởng vẫn đang hỏng',
    (m.O.lineLyDo.textContent || '') === '', m.O.lineLyDo.textContent);
  t('   gửi đúng mã đơn', m.goi[0] && m.goi[0][1] === 'D1', m.goi);
}
/* Đang sửa một dòng thì phải gọi updateLine, không phải addLine — nhầm là đẻ thêm một dòng
   trùng thay vì sửa dòng cũ. */
{
  const m = moi({ f_coso: 'FARM', f_nhom: 'CP', f_nd: 'Nước', f_sl: '1', lineId: 'L9' });
  m.ctx.saveLine();
  t('🔴 đang sửa dòng -> gọi updateLine, không đẻ thêm dòng mới',
    m.goi.length === 1 && m.goi[0][0] === 'updateLine' && m.goi[0][1] === 'L9', m.goi);
}

/* ── 3. 🔴 LỖI NÉM RA GIỮA CHỪNG CŨNG PHẢI THÀNH MỘT CÂU ĐỌC ĐƯỢC ────────────────────────── */
/* Đây là ca "bấm không thấy gì" đúng nghĩa đen: không dòng nào thêm, không câu nào nói. */
{
  const m = moi({ f_coso: 'FARM', f_nhom: 'CP', f_nd: 'Nước', f_sl: '1' });
  m.ctx._guardChi = () => { throw new Error('ô nào đó vừa biến mất'); };
  let vo = false;
  try { m.ctx.saveLine(); } catch (e) { vo = true; }
  t('🔴 lỗi ném giữa chừng KHÔNG được thoát ra ngoài — thoát ra là nút nhấp một cái rồi thôi', !vo);
  t('🔴 và nó phải thành một câu ĐỌC ĐƯỢC, không chết câm',
    /lỗi/i.test(m.O.lineLyDo.textContent || ''), m.O.lineLyDo.textContent);
  t('   câu ấy mang theo lời của lỗi, để còn biết hỏng ở đâu',
    /biến mất/.test(m.O.lineLyDo.textContent || ''), m.O.lineLyDo.textContent);
}

/* ── 4. VẪN NÓI Ở CẢ HAI NƠI — toast cho người đang nhìn góc màn ─────────────────────────── */
{
  const m = moi({});
  m.ctx.saveLine();
  t('🔴 vẫn có toast như cũ, không bỏ mất đường báo nào', m.noi.length > 0, m.noi);
}

if (truot.length) {
  console.log('HỎNG: ' + truot.length);
  truot.forEach(x => console.log('  ✗ ' + x));
  console.log('ĐẠT: ' + dat);
  process.exit(1);
}
console.log('ĐẠT: ' + dat + ' phép — mọi cửa chặn đều nói ra lý do, và lỗi ném ra cũng không chết câm.');
