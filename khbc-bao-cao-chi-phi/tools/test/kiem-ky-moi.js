/*
 * Kiểm: ĐỔI SANG KỲ CHƯA CÓ thì số phải TRỐNG, danh mục phải còn.
 * Anh Thắng 18/09/2026: "ủa, nếu chọn kỳ tháng 9 nó phải trống chứ".
 */
const { chromium } = require('playwright');
const KQ={pass:[],fail:[]};
const ok=(t,c,g)=>(c?KQ.pass:KQ.fail).push(t+(g?' — '+g:''));
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const p=await b.newPage({viewport:{width:1800,height:1000}});
  const loi=[]; p.on('pageerror',e=>loi.push(String(e)));
  p.on('dialog', d=>d.accept());   // đồng ý "dựng kỳ mới từ danh mục"
  await p.goto('http://127.0.0.1:8109/bao-cao-chi-phi/'); await p.waitForTimeout(800);
  if (await p.$('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn');
    await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(900);

  // ⚠️ Ô nhập chỉ tồn tại khi TAB DOANH THU đang mở — đọc lúc tab khác thì đếm ra 0 và bài kiểm
  //    đỏ ở chỗ không có lỗi (đã dính đúng vậy lần đầu).
  const moTabDoanhThu = async () => {
    await p.evaluate(()=>{ const x=[...document.querySelectorAll('#tabs button')].find(b=>/Doanh thu/i.test(b.textContent)); x&&x.click(); });
    await p.waitForTimeout(700);
  };
  // dat ky T08/2026 va bao dam co so
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(500);
  await p.fill('#inpYear','2026'); await p.dispatchEvent('#inpYear','change'); await p.waitForTimeout(2000);
  await moTabDoanhThu();
  /* Gõ số vào hai điểm để kỳ T08 CÓ dữ liệu thật. Không gõ thì T08 cũng vừa được dựng mới nên
     rỗng, và phép "kỳ sau phải trống" thành vô nghĩa — trống vì chẳng có gì để mang sang. */
  await p.evaluate(()=>{
    const o=[...document.querySelectorAll('input[data-path*="sites."][data-path$=".revenue"]')].slice(0,2);
    o.forEach((i,k)=>{ i.value=String((k+1)*111000000); i.dispatchEvent(new Event('change',{bubbles:true})); });
  });
  await p.waitForTimeout(2500);   // chờ tự lưu lên máy chủ
  const t8 = await p.evaluate(()=>{
    const so=[...document.querySelectorAll('input[data-path*="sites."][data-path$=".revenue"]')].map(i=>i.value);
    return { diem: so.length, coSo: so.filter(v=>v && v!=='0' && v!=='0,00').length };
  });
  ok('Kỳ T08 có sẵn số liệu để thử', t8.coSo>0, t8.coSo+'/'+t8.diem+' điểm có doanh thu');

  // doi sang T09 — ky chua co
  await p.selectOption('#selMonth','9'); await p.waitForTimeout(3000);
  await moTabDoanhThu();
  const t9 = await p.evaluate(()=>{
    const so=[...document.querySelectorAll('input[data-path*="sites."][data-path$=".revenue"]')].map(i=>i.value);
    const tenDiem=[...document.querySelectorAll('input[data-path*="sites."][data-path$=".name"]')].map(i=>i.value);
    const ma=[...document.querySelectorAll('input[data-path*="sites."][data-path$=".code"]')].map(i=>i.value);
    return { diem: so.length, coSo: so.filter(v=>v && v!=='0' && v!=='0,00').length,
             tenDiem: tenDiem.filter(Boolean).length, ma: ma.filter(Boolean).length };
  });
  ok('🔴 Kỳ T09 (kỳ mới) — doanh thu TRỐNG', t9.coSo===0, t9.coSo+' điểm còn số');
  ok('Nhưng DANH MỤC điểm bán vẫn còn', t9.diem>0 && t9.tenDiem>0, t9.diem+' điểm · '+t9.tenDiem+' có tên');
  ok('Mã đơn vị vẫn còn', t9.ma>0, t9.ma+' mã');

  // tong cua bo phan cung phai 0
  await p.evaluate(()=>{ const x=[...document.querySelectorAll('#tabs button')].find(b=>/Tổng quan/i.test(b.textContent)); x&&x.click(); });
  await p.waitForTimeout(700);
  const tq = await p.evaluate(()=>document.querySelector('#tab-dashboard').textContent);
  ok('Bảng Tổng quan không còn tổng của kỳ trước',
     !/333\.000\.000|111\.000\.000|222\.000\.000/.test(tq), 'vẫn thấy số của T08');

  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/ky-moi.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
