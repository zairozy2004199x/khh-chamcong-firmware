/* Kiểm "Cứ lấy hết cơ sở đó là được": một nút lấy hết cơ sở của nguồn, kèm bộ phận mặc định. */
const { chromium } = require('playwright');
const KQ={pass:[],fail:[]};
const ok=(t,c,g)=>(c?KQ.pass:KQ.fail).push(t+(g?' — '+g:''));
const mo = async (p,ten) => { await p.evaluate((t)=>{const x=[...document.querySelectorAll('#tabs button')].find(b=>new RegExp(t,'i').test(b.textContent)); x&&x.click();},ten); await p.waitForTimeout(700); };
const PORT = process.env.PORT || '8116';
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const p=await b.newPage({viewport:{width:1900,height:1000}});
  const loi=[]; p.on('pageerror',e=>loi.push(String(e))); p.on('dialog',d=>d.accept());
  await p.goto('http://127.0.0.1:'+PORT+'/bao-cao-chi-phi/'); await p.waitForTimeout(800);
  if (await p.$('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(800);
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(600);
  await p.fill('#inpYear','2026'); await p.dispatchEvent('#inpYear','change'); await p.waitForTimeout(2500);
  // bo sach diem de moi co so ben Ghe deu la "chua ghep"
  await p.evaluate(()=>{const s=window.BaoCaoApp.getState(); s.sites=[]; window.BaoCaoApp.setState(s);});
  await p.waitForTimeout(1500);
  await mo(p,'Doanh thu');
  await p.click('[data-act="napFabi"][data-arg="ghe"]'); await p.waitForTimeout(2500);

  ok('Hộp xem trước có ô "Bộ phận mặc định"', !!(await p.$('select[data-bpmd]')));

  // 🔴 CHUA co diem nao noi nguon nay va chua chon bo phan -> khong nhet bua vao mot bo phan
  const t1 = await p.evaluate(()=>[...document.querySelectorAll('select[data-fabi]')].filter(s=>s.value.indexOf('new:')===0).length);
  /* Nguồn Ghế chỉ thuộc Posh / JP, và chưa có điểm nào để suy ra → lấy bộ phận đầu của nguồn
     (Posh). Nên nạp xong là đã lấy hết, khỏi bấm gì. */
  ok('🔴 Nạp xong là LẤY HẾT ngay, không phải chọn gì', t1===7, t1+'/7 dòng đã chọn tạo mới');

  // chon Posh -> bang CHIA LAI ngay, khong phai bam them nut
  await p.selectOption('select[data-bpmd]','posh'); await p.waitForTimeout(1500);
  const t2 = await p.evaluate(()=>{const ss=[...document.querySelectorAll('select[data-fabi]')];
    return { tao: ss.filter(s=>s.value.indexOf('new:')===0).length, tong: ss.length,
      hint: (()=>{const n=document.querySelector('[data-act="fabiGhi"]'); const c=n&&n.closest('.card');
        const h=c&&c.querySelector('.hint'); return h?h.textContent:'';})() };});
  ok('🔴 Chọn bộ phận mặc định là CHIA LẠI NGAY, lấy hết, khỏi bấm thêm nút',
     t2.tao===t2.tong && t2.tong===7, t2.tao+'/'+t2.tong+' cơ sở');
  ok('Không còn bỏ lại đồng nào', !/bỏ lại/.test(t2.hint), t2.hint.replace(/\s+/g,' ').slice(0,110));

  await p.click('[data-act="fabiGhi"]'); await p.waitForTimeout(3500);
  await mo(p,'Tổng quan');
  const t3 = await p.evaluate(()=>{const s=window.BaoCaoApp.getState(); const R=window.BaoCaoApp.getReport();
    return { diem: s.sites.length, noi: s.sites.filter(x=>(x.gheTen||'').trim()).length,
      tongDT: Object.values(R.revenue).reduce((a,b)=>a+b,0), maTrong: s.sites.filter(x=>!x.code).length,
      canhBao: (document.querySelector('#tab-dashboard .issue.warn')||{}).textContent||'',
      bpmd: (s.options.bpMacDinh||{}).ghe||'' };});
  ok('🔴 Lấy hết: 7 điểm mới, đều nối nguồn Ghế', t3.diem===7 && t3.noi===7, t3.diem+' điểm · '+t3.noi+' nối');
  /* Cộng CẢ BẢY bộ phận: cơ sở nào đoán được theo tên thì vào bộ phận ấy, còn lại vào bộ phận
     mặc định — tổng phải bằng đúng tổng của nguồn, không hụt đồng nào. */
  ok('Doanh thu về ĐỦ bằng tổng của nguồn, không hụt', t3.tongDT===191880000, t3.tongDT);
  ok('Hết báo THIẾU LIÊN KẾT', !/THIẾU LIÊN KẾT/.test(t3.canhBao), t3.canhBao.replace(/\s+/g,' ').slice(0,80));
  ok('🔴 KHÔNG bịa mã đơn vị cho điểm mới', t3.maTrong===7, t3.maTrong+' điểm để trống mã');
  ok('Nhớ bộ phận mặc định cho lần sau', t3.bpmd==='posh', t3.bpmd);

  // ── BỎ HẲN cơ sở không thuộc báo cáo này (anh Thắng: "Cái anh đang làm là MN") ──────────────
  await p.evaluate(()=>{const s=window.BaoCaoApp.getState(); s.sites=[]; s.options.boQuaNguon={};
    window.BaoCaoApp.setState(s);});
  await p.waitForTimeout(1500);
  await mo(p,'Doanh thu');
  await p.click('[data-act="napFabi"][data-arg="ghe"]'); await p.waitForTimeout(2500);
  const ten = await p.evaluate(()=>{const s=document.querySelector('select[data-fabi]');
    s.value='bo_han'; s.dispatchEvent(new Event('change',{bubbles:true}));
    return s.closest('tr').cells[0].textContent.trim();});
  await p.waitForTimeout(1200);
  /* Không bấm nút nào: nạp xong là đã chọn sẵn tạo mới cho mọi cửa hàng chưa ghép. */
  const h1 = await p.evaluate(()=>{const ss=[...document.querySelectorAll('select[data-fabi]')];
    return { tao: ss.filter(s=>s.value.indexOf('new:')===0).length, boHan: ss.filter(s=>s.value==='bo_han').length };});
  ok('🔴 "Lấy hết" KHÔNG kéo cơ sở đã bỏ hẳn vào', h1.tao===6 && h1.boHan===1, h1.tao+' tạo mới · '+h1.boHan+' bỏ hẳn');

  await p.click('[data-act="fabiGhi"]'); await p.waitForTimeout(3500);
  const h2 = await p.evaluate((t)=>{const s=window.BaoCaoApp.getState(); const R=window.BaoCaoApp.getReport();
    return { nho: (s.options.boQuaNguon||{}).gheTen||[], diem: s.sites.length,
      tongDT: Object.values(R.revenue).reduce((a,b)=>a+b,0) };}, ten);
  ok('Nhớ cơ sở đã bỏ hẳn cho kỳ sau', h2.nho.length===1 && h2.nho[0]===ten, JSON.stringify(h2.nho));
  ok('Chỉ tạo điểm cho cơ sở thuộc báo cáo', h2.diem===6, h2.diem+' điểm');
  ok('Doanh thu KHÔNG cộng cơ sở đã bỏ hẳn', h2.tongDT===191880000-102120000, h2.tongDT);

  await mo(p,'Tổng quan');
  const h3 = await p.evaluate(()=>(document.querySelector('#tab-dashboard .issue.warn')||{}).textContent||'');
  ok('🔴 Cơ sở đã bỏ hẳn KHÔNG kêu ở dòng THIẾU LIÊN KẾT', !/THIẾU LIÊN KẾT/.test(h3), h3.replace(/\s+/g,' ').slice(0,80));

  // mo lai nguon: dong bo han van nho, khong hoi lai
  await mo(p,'Doanh thu');
  await p.click('[data-act="napFabi"][data-arg="ghe"]'); await p.waitForTimeout(2500);
  const h4 = await p.evaluate(()=>[...document.querySelectorAll('select[data-fabi]')].filter(s=>s.value==='bo_han').length);
  ok('Mở lại nguồn thì vẫn nhớ, không hỏi lại', h4===1, h4+' dòng giữ trạng thái bỏ hẳn');

  // ── 🔴 TỰ SUY RA bộ phận từ các điểm ĐANG NỐI, khỏi chọn tay ────────────────────────────────
  //    Đúng cảnh của anh Thắng: 40 điểm đã nối Ghế (đều Posh), 16 cửa hàng còn lại bị bỏ lại 696tr
  //    chỉ vì chưa ai chọn "bộ phận mặc định".
  await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    s.options.bpMacDinh={}; s.options.boQuaNguon={};   // quên lựa chọn tay đi
    s.sites=s.sites.slice(0,3);                        // còn 3 điểm đang nối Ghế, đều Posh
    window.BaoCaoApp.setState(s);});
  await p.waitForTimeout(1500);
  await mo(p,'Doanh thu');
  await p.click('[data-act="napFabi"][data-arg="ghe"]'); await p.waitForTimeout(3000);
  const z = await p.evaluate(()=>{const ss=[...document.querySelectorAll('select[data-fabi]')];
    const n=document.querySelector('[data-act="fabiGhi"]'); const c=n&&n.closest('.card');
    const h=c&&c.querySelector('.hint');
    return { chuaGhep: ss.filter(s=>s.value==='').length, tong: ss.length,
      mac: (document.querySelector('select[data-bpmd] option[value=""]')||{}).textContent||'',
      hint: h?h.textContent:'' };});
  ok('🔴 Tự suy bộ phận từ các điểm đang nối — nạp xong là LẤY HẾT, không phải chọn tay',
     z.chuaGhep===0, z.chuaGhep+'/'+z.tong+' cửa hàng còn bỏ lại');
  ok('Không còn dòng "bỏ lại" nào', !/bỏ lại/.test(z.hint), z.hint.replace(/\s+/g,' ').slice(0,110));
  ok('Ô mặc định nói rõ đang tự theo bộ phận nào', /tự theo các điểm đang nối/.test(z.mac), z.mac);

  // ── 🔴 NGUỒN GHẾ KHÔNG ĐƯỢC CHẢY SANG BỘ PHẬN KHÁC ──────────────────────────────────────────
  await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    s.options.bpMacDinh={}; s.options.boQuaNguon={};
    s.sites=[{dept:'event',code:'EVAMBT',name:'AEON MALL BÌNH TÂN',revenue:0,gheTen:'AEON MALL BÌNH TÂN'}];
    window.BaoCaoApp.setState(s);});
  await p.waitForTimeout(1500);
  await mo(p,'Doanh thu');
  await p.click('[data-act="napFabi"][data-arg="ghe"]'); await p.waitForTimeout(3000);
  const w = await p.evaluate(()=>{
    const tr=[...document.querySelectorAll('select[data-fabi]')].map(s=>s.closest('tr'))
      .find(r=>/AEON MALL BÌNH TÂN/.test(r.cells[0].textContent));
    const sel=tr&&tr.querySelector('select');
    return { noiVaoEvent: sel? sel.value : '?',
      bpTaoMoi: sel? [...sel.querySelectorAll('optgroup[label*="Tạo điểm"] option')].map(o=>o.textContent.split('—')[0].trim()) : [] };});
  ok('🔴 Cơ sở Ghế KHÔNG ghép vào điểm Event, kể cả liên kết cũ đã lưu',
     w.noiVaoEvent.indexOf('new:')===0 || w.noiVaoEvent==='', 'ô chọn = '+w.noiVaoEvent);
  ok('🔴 Ô "tạo điểm mới" chỉ còn Posh / JP', w.bpTaoMoi.length===2 && /Posh/.test(w.bpTaoMoi[0]),
     w.bpTaoMoi.join(' · '));

  await mo(p,'Doanh thu');
  const w2 = await p.evaluate(()=>(document.querySelector('#tab-revenue .issue.error')||{}).textContent||'');
  ok('🔴 Báo đỏ điểm đang nối sai bộ phận, kèm nút gỡ', /nối SAI BỘ PHẬN/.test(w2),
     w2.replace(/\s+/g,' ').slice(0,120));
  const coNut = await p.$('[data-act="goSaiBp"]');
  ok('Có nút "Gỡ liên kết sai & xoá số ghi nhầm"', !!coNut);

  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/lay-het.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
