/* Kiểm LIÊN KẾT SỐNG: nối một lần rồi số tự về, không phải bấm lại. */
const { chromium } = require('playwright');
const KQ={pass:[],fail:[]};
const ok=(t,c,g)=>(c?KQ.pass:KQ.fail).push(t+(g?' — '+g:''));
const moDT = async (p) => { await p.evaluate(()=>{ const x=[...document.querySelectorAll('#tabs button')].find(b=>/Doanh thu/i.test(b.textContent)); x&&x.click(); }); await p.waitForTimeout(700); };
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const p=await b.newPage({viewport:{width:1900,height:1000}});
  const loi=[]; p.on('pageerror',e=>loi.push(String(e)));
  p.on('dialog', d=>d.accept());
  await p.goto('http://127.0.0.1:8110/bao-cao-chi-phi/'); await p.waitForTimeout(800);
  if (await p.$('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(800);
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(600);
  await p.fill('#inpYear','2026'); await p.dispatchEvent('#inpYear','change'); await p.waitForTimeout(2500);
  await moDT(p);

  ok('Có công tắc "Tự lấy"', await p.evaluate(()=>!!document.querySelector('[data-act="fabiTuDong"]')));
  ok('Có cột "Nguồn" trên bảng điểm bán',
     await p.evaluate(()=>[...document.querySelectorAll('th')].some(t=>t.textContent.trim()==='Nguồn')));

  // noi lan dau qua man xem truoc
  await p.click('[data-act="napFabi"]'); await p.waitForTimeout(2500);
  const soGhep = await p.evaluate(()=>document.querySelectorAll('select[data-fabi]').length);
  await p.click('[data-act="fabiGhi"]'); await p.waitForTimeout(1800);
  const sau = await p.evaluate(()=>({
    batTuDong: /BẬT/.test((document.querySelector('[data-act="fabiTuDong"]')||{}).textContent||''),
    lienKet: document.querySelectorAll('[data-act="goLienKet"]').length,
    khoa: [...document.querySelectorAll('input[data-path*="sites."][data-path$=".revenue"]')].filter(i=>i.readOnly).length,
  }));
  ok('Nối xong thì TỰ BẬT chế độ tự lấy', sau.batTuDong);
  ok('Các điểm đã nối hiện 🔗 ở cột Nguồn', sau.lienKet>0, sau.lienKet+' điểm');
  ok('Ô doanh thu của điểm đã nối thành chỉ-đọc', sau.khoa===sau.lienKet, sau.khoa+'/'+sau.lienKet);

  // xoa so di, doi ky di roi ve — so phai TU VE
  await p.evaluate(()=>{
    const o=[...document.querySelectorAll('input[data-path*="sites."][data-path$=".revenue"]')].filter(i=>i.readOnly);
    return o.length;
  });
  const truoc = await p.evaluate(()=>[...document.querySelectorAll('input[data-path*="sites."][data-path$=".revenue"]')].filter(i=>i.readOnly).map(i=>i.value));
  await p.selectOption('#selMonth','7'); await p.waitForTimeout(2500);
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(3000);
  await moDT(p);
  const veLai = await p.evaluate(()=>[...document.querySelectorAll('input[data-path*="sites."][data-path$=".revenue"]')].filter(i=>i.readOnly).map(i=>i.value));
  ok('🔴 Đổi kỳ đi rồi về: số TỰ VỀ, không phải bấm lại',
     veLai.length>0 && JSON.stringify(veLai)===JSON.stringify(truoc), veLai.slice(0,3).join(' · '));
  const tt = await p.evaluate(()=>(document.querySelector('#tab-revenue .issue')||{}).textContent||'');
  /* Chữ của dòng trạng thái đổi từ 1.13.0 (tách theo nguồn: "N điểm ← Doanh thu FABi") — bám
     theo chữ mới, đừng bám câu cũ rồi báo đỏ ở chỗ không có lỗi. */
  ok('Có dòng trạng thái nói đang nối bao nhiêu điểm', /\d+ điểm ← Doanh thu FABi/.test(tt), tt.replace(/\s+/g,' ').slice(0,90));

  // go lien ket thi quay lai go tay
  const truocGo = await p.evaluate(()=>document.querySelectorAll('[data-act="goLienKet"]').length);
  if (truocGo) {
    await p.click('[data-act="goLienKet"]'); await p.waitForTimeout(1200);
    const goRoi = await p.evaluate(()=>document.querySelectorAll('[data-act="goLienKet"]').length);
    ok('Gỡ liên kết được', goRoi===truocGo-1, truocGo+' -> '+goRoi);
  } else {
    ok('Gỡ liên kết được', false, 'không thấy nút gỡ sau khi quay lại kỳ');
  }

  // ── 🔴 MỞ LẠI TRANG Ở ĐÚNG KỲ ẤY: vẫn phải tự lấy, không đợi đổi kỳ ────────────────────────
  //    Anh Thắng 18/09/2026: "các lần sau nó tự đẩy qua luôn hay phải bấm nạp".
  await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    const i=s.sites.findIndex(x=>(x.fabiTen||'').trim());
    if(i>=0) s.sites[i].revenue = 1;      // phá số đi, mở lại trang phải tự kéo về
    window.BaoCaoApp.setState(s);});
  await p.waitForTimeout(2500);
  await p.reload(); await p.waitForTimeout(1200);
  /* Sau khi tải lại, phiên vẫn còn nên ô PIN có trong DOM mà ẩn — phải hỏi "có HIỆN không". */
  if (await p.isVisible('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(5000);
  await moDT(p);
  const mo = await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    const x=s.sites.find(y=>(y.fabiTen||'').trim());
    return { rev: x?x.revenue:0, tt: (document.querySelector('#tab-revenue .issue')||{}).textContent||'' };});
  ok('🔴 Mở lại trang ở đúng kỳ ấy vẫn TỰ LẤY, không phải bấm Nạp', mo.rev>1, 'doanh thu = '+mo.rev);
  ok('Dòng trạng thái nói rõ nhịp tự lấy', /10 phút một lần/.test(mo.tt), mo.tt.replace(/\s+/g,' ').slice(0,120));

  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/fabi-live.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
