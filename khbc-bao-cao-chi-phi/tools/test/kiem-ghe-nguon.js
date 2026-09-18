/* Kiểm nguồn thứ hai: doanh thu Posh/JP lấy từ plugin Ghế Massage, cùng lối liên kết sống. */
const { chromium } = require('playwright');
const KQ={pass:[],fail:[]};
const ok=(t,c,g)=>(c?KQ.pass:KQ.fail).push(t+(g?' — '+g:''));
const moDT = async (p) => { await p.evaluate(()=>{const x=[...document.querySelectorAll('#tabs button')].find(b=>/Doanh thu/i.test(b.textContent)); x&&x.click();}); await p.waitForTimeout(700); };
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const p=await b.newPage({viewport:{width:1900,height:1000}});
  const loi=[]; p.on('pageerror',e=>loi.push(String(e))); p.on('dialog',d=>d.accept());
  await p.goto('http://127.0.0.1:8112/bao-cao-chi-phi/'); await p.waitForTimeout(800);
  if (await p.$('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(800);
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(600);
  await p.fill('#inpYear','2026'); await p.dispatchEvent('#inpYear','change'); await p.waitForTimeout(2500);
  await moDT(p);

  const nut = await p.evaluate(()=>[...document.querySelectorAll('[data-act="napFabi"]')].map(b=>b.dataset.arg+':'+b.textContent.trim()));
  ok('Có HAI nút nạp, hai nguồn', nut.length===2 && nut.some(x=>x.startsWith('ghe')), nut.join(' | '));

  // nap tu GHE
  await p.click('[data-act="napFabi"][data-arg="ghe"]'); await p.waitForTimeout(2500);
  const g1 = await p.evaluate(()=>{
    const tr=[...document.querySelectorAll('select[data-fabi]')].map(s=>s.closest('tr'));
    return { so: tr.length, tieuDe: (document.querySelector('#tab-revenue h2')||{}).textContent||'',
      cot: [...document.querySelectorAll('#tab-revenue thead th')].map(t=>t.textContent.trim()).slice(0,1).join(''),
      dau: tr.length? tr[0].cells[0].textContent.trim()+' = '+tr[0].cells[1].textContent.trim() : '' };
  });
  ok('Đọc được 7 cơ sở từ plugin Ghế', g1.so===7, g1.so+' dòng · '+g1.dau);
  ok('Cộng đúng theo kỳ (loại kỳ khác + dòng rác)', /102\.120\.000/.test(g1.dau), g1.dau);

  await p.click('[data-act="fabiTaoHet"]').catch(()=>{}); await p.waitForTimeout(900);
  await p.click('[data-act="fabiGhi"]'); await p.waitForTimeout(2000);
  await moDT(p);
  const g2 = await p.evaluate(()=>{
    const n=[...document.querySelectorAll('#tab-revenue tbody td')].map(t=>t.textContent);
    return { ghe: n.filter(t=>/🔗 Ghế/.test(t)).length, fabi: n.filter(t=>/🔗 FABi/.test(t)).length,
      tt: (document.querySelector('#tab-revenue .issue')||{}).textContent||'' };
  });
  ok('Điểm nối nguồn Ghế hiện "🔗 Ghế"', g2.ghe>0, g2.ghe+' điểm');
  ok('Dòng trạng thái tách riêng hai nguồn', /Ghế Massage/.test(g2.tt), g2.tt.replace(/\s+/g,' ').slice(0,110));

  // nap them tu FABi -> hai nguon song song
  await p.click('[data-act="napFabi"][data-arg="fabi"]'); await p.waitForTimeout(2500);
  await p.click('[data-act="fabiTaoHet"]').catch(()=>{}); await p.waitForTimeout(900);
  await p.click('[data-act="fabiGhi"]'); await p.waitForTimeout(2000);
  await moDT(p);
  const g3 = await p.evaluate(()=>{
    const n=[...document.querySelectorAll('#tab-revenue tbody td')].map(t=>t.textContent);
    return { ghe: n.filter(t=>/🔗 Ghế/.test(t)).length, fabi: n.filter(t=>/🔗 FABi/.test(t)).length,
      tt: (document.querySelector('#tab-revenue .issue')||{}).textContent||'' };
  });
  ok('Hai nguồn chạy song song', g3.ghe>0 && g3.fabi>0, 'Ghế '+g3.ghe+' · FABi '+g3.fabi);
  ok('🔴 Không điểm nào nối cả hai nguồn cùng lúc',
     await p.evaluate(()=>{const S=[...document.querySelectorAll('#tab-revenue tbody tr')];
       return !S.some(r=>/🔗 Ghế/.test(r.textContent) && /🔗 FABi/.test(r.textContent));}));

  // doi ky di roi ve: ca hai nguon tu ve
  const truoc = await p.evaluate(()=>[...document.querySelectorAll('input[data-path*="sites."][data-path$=".revenue"]')].filter(i=>i.readOnly).length);
  await p.selectOption('#selMonth','7'); await p.waitForTimeout(2500);
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(3500);
  await moDT(p);
  const sau = await p.evaluate(()=>[...document.querySelectorAll('input[data-path*="sites."][data-path$=".revenue"]')].filter(i=>i.readOnly).length);
  ok('Đổi kỳ đi rồi về: cả hai nguồn tự lấy lại', sau===truoc && sau>0, truoc+' -> '+sau+' ô khoá');

  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/ghe-nguon.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
