/* Kiểm phần tiền bên nguồn: hộp xem trước phải nói rõ TỔNG CỦA NGUỒN và phần SẼ BỎ LẠI, và sau
   khi ghi thì không còn đồng nào rơi ra ngoài (từ 1.29.0 cơ sở mới tự vào, không phải bấm Nạp). */
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
  if (await p.isVisible('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(800);
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(600);
  await p.fill('#inpYear','2026'); await p.dispatchEvent('#inpYear','change'); await p.waitForTimeout(2500);
  await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    s.sites=[]; s.options.bpMacDinh={}; s.options.boQuaNguon={}; s.options.fabiTuDong=false;
    window.BaoCaoApp.setState(s);});
  await p.waitForTimeout(1500);
  await mo(p,'Doanh thu');

  await p.click('[data-act="napFabi"][data-arg="ghe"]'); await p.waitForTimeout(2500);
  const hint = () => p.evaluate(()=>{const n=document.querySelector('[data-act="fabiGhi"]');
    const c=n&&n.closest('.card'); const h=c&&c.querySelector('.hint'); return h?h.textContent:'';});

  const x1 = await hint();
  ok('Hộp xem trước ghi rõ TỔNG CỦA NGUỒN', /nguồn có/i.test(x1), x1.replace(/\s+/g,' ').slice(0,120));
  ok('Nạp xong là lấy hết, không còn bỏ lại', !/bỏ lại/i.test(x1), x1.replace(/\s+/g,' ').slice(0,120));

  // bo bot vai dong ra -> phai ghi ro phan SE BO LAI
  await p.evaluate(()=>{const ss=[...document.querySelectorAll('select[data-fabi]')];
    ss.forEach((s,i)=>{ if(i>0){ s.value=''; s.dispatchEvent(new Event('change',{bubbles:true})); } });});
  await p.waitForTimeout(1500);
  const x2 = await hint();
  ok('Bỏ bớt dòng ra thì ghi rõ phần SẼ BỎ LẠI', /bỏ lại/i.test(x2), x2.replace(/\s+/g,' ').slice(0,130));

  await p.click('[data-act="fabiGhi"]'); await p.waitForTimeout(3500);
  const g1 = await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    return { diem: s.sites.length, tuDong: s.options.fabiTuDong };});
  ok('Ghi xong thì tự bật Tự lấy', g1.tuDong===true, String(g1.tuDong));
  /* Ghi xong là chạy luôn một lượt tự lấy, và lượt ấy nối nốt mấy dòng vừa bỏ ra — đúng ý
     "tự động luôn, không cần bấm nạp". Muốn một cơ sở ĐỨNG NGOÀI hẳn thì chọn "🚫 Bỏ hẳn". */
  ok('Ghi xong là tự nối nốt phần bỏ ra (trừ "bỏ hẳn")', g1.diem===7, g1.diem+' điểm');

  // 🔴 tu 1.29.0: lan lay tu dong ke tiep TU NOI not phan con lai — khong phai bam Nap
  await p.reload(); await p.waitForTimeout(1200);
  if (await p.isVisible('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(6000);
  await mo(p,'Doanh thu');
  const g2 = await p.evaluate(()=>{const s=window.BaoCaoApp.getState(); const R=window.BaoCaoApp.getReport();
    return { diem: s.sites.length, tong: Object.values(R.revenue).reduce((a,c)=>a+c,0),
      tt: (document.querySelector('#tab-revenue .issue')||{}).textContent||'' };});
  ok('🔴 Lượt tự lấy sau TỰ NỐI nốt phần còn lại', g2.diem===7, g2.diem+' điểm');
  ok('Doanh thu về đủ bằng tổng của nguồn', g2.tong===191880000, String(g2.tong));
  ok('Không còn đồng nào rơi ra ngoài', !/THIẾU LIÊN KẾT/.test(g2.tt), g2.tt.replace(/\s+/g,' ').slice(0,90));

  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/so-du-nguon.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
