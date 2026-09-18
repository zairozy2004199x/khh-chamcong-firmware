/*
 * Kiểm đường NẠP DOANH THU TỪ "DOANH THU FABi" trên app THẬT (Chromium + backend PHP giả lập).
 * Bảng FABi giả dựng bằng /__dev/fabi — 13 quán đúng tên trang khmatrix.com/doanh-thu-hcm,
 * mỗi quán 2 dòng trong kỳ + 1 dòng kỳ KHÁC (để bắt lỗi cộng nhầm kỳ).
 */
const { chromium } = require('playwright');
const KQ={pass:[],fail:[]};
const ok=(t,c,g)=>(c?KQ.pass:KQ.fail).push(t+(g?' — '+g:''));
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const p=await b.newPage({viewport:{width:1800,height:1000}});
  const loi=[]; p.on('pageerror',e=>loi.push(String(e)));
  await p.goto('http://127.0.0.1:8108/bao-cao-chi-phi/'); await p.waitForTimeout(800);
  if (await p.$('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn');
    await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(800);

  // dat ky 8/2026 cho khop du lieu FABi
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(400);
  await p.fill('#inpYear','2026'); await p.dispatchEvent('#inpYear','change'); await p.waitForTimeout(900);

  // sang tab Doanh thu
  await p.evaluate(()=>{ const x=[...document.querySelectorAll('#tabs button')].find(b=>/Doanh thu/i.test(b.textContent)); x&&x.click(); });
  await p.waitForTimeout(700);
  ok('Có nút "Nạp từ Doanh thu FABi"', await p.evaluate(()=>!!document.querySelector('[data-act="napFabi"]')));

  await p.click('[data-act="napFabi"]');
  await p.waitForTimeout(2500);
  const r = await p.evaluate(()=>{
    const sel=[...document.querySelectorAll('select[data-fabi]')];
    const rows=sel.map(s=>{ const tr=s.closest('tr'); return {
      quan: tr.cells[0].textContent.trim(),
      tien: tr.cells[1].textContent.trim(),
      diem: s.options[s.selectedIndex] ? s.options[s.selectedIndex].text : '',
      viSao: tr.cells[4].textContent.trim() };});
    const head=(document.querySelector('[data-act="fabiGhi"]')||{}).textContent||'';
    return { so: sel.length, rows, head, loi: (document.querySelector('.issue.error')||{}).textContent||'' };
  });
  ok('Đọc được 13 cửa hàng từ bảng FABi', r.so===13, r.so+' dòng · '+(r.loi||''));
  const q=(t)=>r.rows.find(x=>x.quan.indexOf(t)===0);
  // 🔴 CONG THEO KY: moi quan 2 dong trong ky (0.4+0.6) + 1 dong thang 7 phai BI LOAI
  ok('Cộng đúng theo kỳ, không lẫn kỳ khác',
     q('TuTu Train - Aeon Tân Phú') && /29\.905\.000/.test(q('TuTu Train - Aeon Tân Phú').tien),
     q('TuTu Train - Aeon Tân Phú') ? q('TuTu Train - Aeon Tân Phú').tien : '?');
  ok('Có ghép sẵn một số điểm', r.rows.filter(x=>!/chưa ghép/.test(x.viSao)).length>0,
     r.rows.filter(x=>!/chưa ghép/.test(x.viSao)).length+' dòng đã ghép');
  ok('Nút Ghi nói rõ sẽ ghi bao nhiêu điểm', /Ghi \d+ điểm/.test(r.head), r.head.trim());

  // sua tay mot dong roi ghi
  const truoc = await p.evaluate(()=>document.querySelectorAll('select[data-fabi]').length);
  await p.evaluate(()=>{
    const s=[...document.querySelectorAll('select[data-fabi]')].find(x=>x.value==='');
    if(s){ s.value=[...s.options].find(o=>o.value!=='').value; s.dispatchEvent(new Event('change',{bubbles:true})); }
  });
  await p.waitForTimeout(600);
  const sau = await p.evaluate(()=>({
    tay: [...document.querySelectorAll('select[data-fabi]')].map(s=>s.closest('tr').cells[4].textContent.trim()).filter(t=>/anh chọn/.test(t)).length,
    head: (document.querySelector('[data-act="fabiGhi"]')||{}).textContent||'' }));
  ok('Sửa tay được và đánh dấu "anh chọn"', sau.tay===1, sau.tay+' dòng');

  await p.click('[data-act="fabiGhi"]');
  await p.waitForTimeout(1200);
  const sauGhi = await p.evaluate(()=>({
    conBang: !!document.querySelector('select[data-fabi]'),
    toast: (document.querySelector('.toast, #toast')||{}).textContent||'',
    coSo: [...document.querySelectorAll('input[data-path*=".revenue"]')].map(i=>i.value).filter(v=>v&&v!=='0').length
  }));
  ok('Ghi xong thì đóng bảng xem trước', !sauGhi.conBang);
  ok('Có điểm đã nhận doanh thu', sauGhi.coSo>0, sauGhi.coSo+' điểm có số');
  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/fabi.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
