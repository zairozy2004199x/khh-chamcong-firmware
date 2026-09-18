/* Kiểm "SAI KỲ": đổi ô chọn tháng chỉ đổi NHÃN, số vẫn là của kỳ cũ.
   Đúng cái anh Thắng thấy: nhãn T09/2026 mà cột nhập tay + lương là nguyên số T08. */
const { chromium } = require('playwright');
const KQ={pass:[],fail:[]};
const ok=(t,c,g)=>(c?KQ.pass:KQ.fail).push(t+(g?' — '+g:''));
const moTQ = async (p) => { await p.evaluate(()=>{const x=[...document.querySelectorAll('#tabs button')].find(b=>/Tổng quan/i.test(b.textContent)); x&&x.click();}); await p.waitForTimeout(600); };
const PORT = process.env.PORT || '8114';
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const p=await b.newPage({viewport:{width:1900,height:1000}});
  const loi=[]; p.on('pageerror',e=>loi.push(String(e)));
  await p.goto('http://127.0.0.1:'+PORT+'/bao-cao-chi-phi/'); await p.waitForTimeout(800);
  p.on('dialog',d=>d.accept());
  if (await p.$('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(1200);
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(600);
  await p.fill('#inpYear','2026'); await p.dispatchEvent('#inpYear','change'); await p.waitForTimeout(2500);
  // nap mau de co so cua T08
  await p.evaluate(()=>{const m=[...document.querySelectorAll('[data-act]')].find(b=>b.dataset.act==='sample'); m&&m.click();});
  await p.waitForTimeout(2500);
  await moTQ(p);
  const t8 = await p.evaluate(()=>({ ky: window.BaoCaoApp.getState().soCuaKy,
    banner: !!document.querySelector('#tab-dashboard .issue.error') }));
  ok('Kỳ vừa nạp: có dấu kỳ, không báo SAI KỲ', t8.ky==='2026-08' && !t8.banner, 'soCuaKy='+t8.ky);

  // ── 🔴 đổi sang T09 rồi BẤM HUỶ ở hộp "dựng kỳ mới" ──────────────────────────────────────────
  p.removeAllListeners('dialog'); p.on('dialog',d=>d.dismiss());
  await p.selectOption('#selMonth','9'); await p.waitForTimeout(4000);
  await moTQ(p);
  const t9 = await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    return { thang: s.period.month, ky: s.soCuaKy, chon: document.querySelector('#selMonth').value,
      nhan: (document.querySelector('#tab-dashboard .kpi .k')||{}).textContent||'',
      banner: (document.querySelector('#tab-dashboard .issue.error')||{}).textContent||'' };});
  ok('🔴 Bấm Huỷ thì QUAY VỀ kỳ cũ, không để số kỳ cũ đội tên kỳ mới',
     t9.thang===8 && t9.chon==='8', 'đang ở T'+t9.thang+' · ô chọn '+t9.chon);
  ok('Quay về rồi thì không còn lệch kỳ', t9.ky==='2026-08' && !t9.banner, 'soCuaKy='+t9.ky);

  // ── đúng hình dạng trong ảnh: máy chủ ĐÃ giữ sẵn một kỳ T09 mang nguyên số của T08 ──────────
  //    (bản trước đẩy được mớ trộn hai kỳ ấy lên, nên phải bắt được cả những kỳ đã lỡ lưu)
  await p.evaluate(async ()=>{const s=window.BaoCaoApp.getState();
    s.period={month:9,year:2026}; s.soCuaKy='2026-08'; delete s.costItems;
    await window.BaoCaoApi.call('saveState',{period:'2026-09',state:s,version:0});});
  await p.waitForTimeout(800);
  p.removeAllListeners('dialog'); p.on('dialog',d=>d.accept());
  await p.goto('http://127.0.0.1:'+PORT+'/bao-cao-chi-phi/'); await p.waitForTimeout(1500);
  await p.selectOption('#selMonth','9'); await p.waitForTimeout(4000);
  await moTQ(p);
  const x = await p.evaluate(()=>({
    banner: (document.querySelector('#tab-dashboard .issue.error')||{}).textContent||'',
    nut: [...document.querySelectorAll('#tab-dashboard .issue.error button')].map(b=>b.dataset.act),
    thang: window.BaoCaoApp.getState().period.month }));
  ok('🔴 Kỳ đã lỡ lưu số của kỳ khác: Tổng quan phải BÁO ĐỎ ngay trên đầu',
     /SAI KỲ/.test(x.banner) && /2026-08/.test(x.banner) && /2026-09/.test(x.banner),
     x.banner.replace(/\s+/g,' ').slice(0,120));
  ok('Có lối thoát: dựng kỳ mới hoặc quay về kỳ cũ',
     x.nut.includes('dungKyMoi') && x.nut.includes('veKyCu'), x.nut.join(' · '));

  // nut "dung ky moi" -> xoa so, het lech
  await p.click('#tab-dashboard [data-act="dungKyMoi"]'); await p.waitForTimeout(2500);
  await moTQ(p);
  const d = await p.evaluate(()=>{const s=window.BaoCaoApp.getState(); const R=window.BaoCaoApp.getReport();
    return { ky: s.soCuaKy, thang: s.period.month, banner: !!document.querySelector('#tab-dashboard .issue.error'),
      luong: R.salarySitesTotal.actual, diem: s.sites.length };});
  ok('🔴 "Dựng kỳ mới" đưa MỌI SỐ về 0 nhưng GIỮ danh mục',
     d.luong===0 && d.diem>0, 'lương '+d.luong+' · '+d.diem+' điểm còn nguyên');
  ok('Dựng xong thì hết báo SAI KỲ', d.ky==='2026-09' && d.thang===9 && !d.banner, 'soCuaKy='+d.ky);

  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/lech-ky.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
