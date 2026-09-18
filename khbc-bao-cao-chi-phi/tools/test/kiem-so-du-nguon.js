/* Kiểm "THIẾU LIÊN KẾT": tiền bên nguồn chưa nối vào điểm nào phải hiện thành SỐ, không im lặng.
   Đúng cái anh Thắng thấy: trang Ghế tổng 1.216.383.000, báo cáo chỉ 546.005.000. */
const { chromium } = require('playwright');
const KQ={pass:[],fail:[]};
const ok=(t,c,g)=>(c?KQ.pass:KQ.fail).push(t+(g?' — '+g:''));
const mo = async (p,ten) => { await p.evaluate((t)=>{const x=[...document.querySelectorAll('#tabs button')].find(b=>new RegExp(t,'i').test(b.textContent)); x&&x.click();},ten); await p.waitForTimeout(700); };
const PORT = process.env.PORT || '8115';
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const p=await b.newPage({viewport:{width:1900,height:1000}});
  const loi=[]; p.on('pageerror',e=>loi.push(String(e))); p.on('dialog',d=>d.accept());
  await p.goto('http://127.0.0.1:'+PORT+'/bao-cao-chi-phi/'); await p.waitForTimeout(800);
  if (await p.$('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(800);
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(600);
  await p.fill('#inpYear','2026'); await p.dispatchEvent('#inpYear','change'); await p.waitForTimeout(2500);
  await mo(p,'Doanh thu');

  // nap tu Ghe: hop xem truoc phai noi ro tong cua nguon va phan se bo lai
  await p.click('[data-act="napFabi"][data-arg="ghe"]'); await p.waitForTimeout(2500);
  /* Lấy đúng thẻ của HỘP XEM TRƯỚC (thẻ có nút Ghi), chứ #tab-revenue có nhiều thẻ. */
  const x1 = await p.evaluate(()=>{const nut=document.querySelector('[data-act="fabiGhi"]');
    const the=nut&&nut.closest('.card'); const h=the&&the.querySelector('.hint');
    return { hint: h?h.textContent:'' };});
  ok('Hộp xem trước ghi rõ TỔNG CỦA NGUỒN', /nguồn có/i.test(x1.hint), x1.hint.replace(/\s+/g,' ').slice(0,120));
  ok('Và ghi rõ phần SẼ BỎ LẠI khi chưa ghép hết', /bỏ lại/i.test(x1.hint), x1.hint.replace(/\s+/g,' ').slice(0,120));

  // chi ghep MOT co so roi Ghi -> phan con lai phai bao THIEU LIEN KET
  await p.evaluate(()=>{const ss=[...document.querySelectorAll('select[data-luong], select[data-fabi]')];
    ss.forEach((s,i)=>{ if(i>0){ s.value=''; s.dispatchEvent(new Event('change',{bubbles:true})); } });});
  await p.waitForTimeout(1200);
  const conChon = await p.evaluate(()=>[...document.querySelectorAll('select[data-fabi]')].filter(s=>s.value!=='').length);
  await p.click('[data-act="fabiGhi"]'); await p.waitForTimeout(3500);

  await mo(p,'Tổng quan');
  const x2 = await p.evaluate(()=>{const e=document.querySelector('#tab-dashboard .issue.warn');
    return { banner: e?e.textContent:'', nut: e?[...e.querySelectorAll('button')].map(b=>b.dataset.act):[] };});
  ok('🔴 Tổng quan báo THIẾU LIÊN KẾT bằng SỐ, không im lặng',
     /THIẾU LIÊN KẾT/.test(x2.banner) && /chưa nối vào điểm bán nào/.test(x2.banner),
     x2.banner.replace(/\s+/g,' ').slice(0,130));
  ok('Nói rõ nguồn nào và tổng của nguồn là bao nhiêu',
     /Ghế Massage/.test(x2.banner) && /tổng/.test(x2.banner), 'chỉ ghép '+conChon+' cơ sở');
  ok('Có nút sang thẳng tab Doanh thu để ghép', x2.nut.includes('moDoanhThu'), x2.nut.join(' · '));

  await p.click('#tab-dashboard [data-act="moDoanhThu"]'); await p.waitForTimeout(900);
  ok('Nút đưa đúng sang tab Doanh thu',
     await p.evaluate(()=>!document.querySelector('#tab-revenue').hidden));

  // ghep NOT -> het bao
  await p.click('[data-act="napFabi"][data-arg="ghe"]'); await p.waitForTimeout(2500);
  await p.click('[data-act="fabiTaoHet"]').catch(()=>{}); await p.waitForTimeout(900);
  await p.click('[data-act="fabiGhi"]'); await p.waitForTimeout(3500);
  await mo(p,'Tổng quan');
  const x3 = await p.evaluate(()=>{const e=document.querySelector('#tab-dashboard .issue.warn');
    return e?e.textContent:'';});
  ok('Ghép nốt thì hết báo thiếu liên kết', !/THIẾU LIÊN KẾT/.test(x3), x3.replace(/\s+/g,' ').slice(0,90));

  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/so-du-nguon.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
