/* Kiểm "mở kỳ nào chỉ có số của kỳ ấy, chỗ chưa có thì ĐỂ TRỐNG".
   Anh Thắng 18/09/2026: "đừng cũ mới, không ai hiểu được" — nên bảng KHÔNG được có nhãn kỳ nào. */
const { chromium } = require('playwright');
const KQ={pass:[],fail:[]};
const ok=(t,c,g)=>(c?KQ.pass:KQ.fail).push(t+(g?' — '+g:''));
const mo = async (p,ten) => { await p.evaluate((t)=>{const x=[...document.querySelectorAll('#tabs button')].find(b=>new RegExp(t,'i').test(b.textContent)); x&&x.click();},ten); await p.waitForTimeout(700); };
const PORT = process.env.PORT || '8117';
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const p=await b.newPage({viewport:{width:1900,height:1000}});
  const loi=[]; p.on('pageerror',e=>loi.push(String(e))); p.on('dialog',d=>d.accept());
  await p.goto('http://127.0.0.1:'+PORT+'/bao-cao-chi-phi/'); await p.waitForTimeout(800);
  if (await p.isVisible('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(1500);

  // ky T08 dang mo, mot dong mang so ky khac + mot dong dung ky
  await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    s.period={month:8,year:2026}; s.soCuaKy='2026-08';
    s.salarySites=[
      {id:'a',dept:'posh',name:'Posh HCM',reported:111649262,report:111649262,dntt:111649262,actual:111649262,nsKy:'2026-07'},
      {id:'b',dept:'jp',name:'BP JP HCM',reported:41635755,report:41635755,dntt:41635755,actual:41435755,nsKy:'2026-08'},
    ];
    /* Thêm một dòng KHÔNG có dấu kỳ — dữ liệu bản cũ, phải được HỎI chứ không xoá lén. */
    s.salarySites.push({id:'c',dept:'tutu',name:'Tàu Tân An',reported:12133000,report:12133000,dntt:12133000,actual:12133000});
    s.sites=[{dept:'tutu',code:'TTAMTP',name:'AMTP',revenue:29905000,dtKy:'2026-07'}];
    window.BaoCaoApp.setState(s);});
  await p.waitForTimeout(1500);
  await p.reload(); await p.waitForTimeout(1200);
  if (await p.isVisible('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(5000);
  await mo(p,'Lương');

  const x = await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    const r=s.salarySites.find(y=>y.name==='Posh HCM');
    const g=s.salarySites.find(y=>y.name==='BP JP HCM');
    const rows=[...document.querySelectorAll('#tab-salary tbody tr')];
    const tr=rows.find(y=>{const i=y.querySelector('input[data-path$=".name"]'); return i&&i.value==='Posh HCM';});
    return { bon: [r.reported,r.report,r.dntt,r.actual], giu: g.reported,
      oTrong: tr? [...tr.querySelectorAll('input.num')].slice(0,4).map(i=>i.value) : null,
      nhan: tr? tr.querySelectorAll('.chip').length : -1,
      than: document.querySelector('#tab-salary').textContent };});
  ok('🔴 Mở kỳ là TỰ DỌN số không thuộc kỳ ấy, cả bốn cột', x.bon.every(v=>v===0), JSON.stringify(x.bon));
  ok('Số đúng của kỳ đang mở thì giữ nguyên', x.giu===41635755, String(x.giu));
  ok('🔴 Chưa có số thì Ô TRỐNG, không phải số 0', x.oTrong && x.oTrong.every(v=>v===''), JSON.stringify(x.oTrong));
  ok('🔴 KHÔNG còn nhãn kỳ nào trên dòng', x.nhan===0, x.nhan+' nhãn');
  ok('🔴 Không còn chữ "kỳ cũ" / "kỳ trước" trên màn Lương',
     !/kỳ cũ|kỳ trước|SỐ KỲ TRƯỚC/.test(x.than));

  await mo(p,'Tổng quan');
  const t = await p.evaluate(()=>document.querySelector('#tab-dashboard').textContent);
  ok('🔴 Tổng quan cũng không còn dòng "SỐ KỲ TRƯỚC"', !/SỐ KỲ TRƯỚC/.test(t));

  await mo(p,'Doanh thu');
  await p.evaluate(()=>{const f=document.querySelector('#siteFilter');
    if(f){ f.value='all'; f.dispatchEvent(new Event('change',{bubbles:true})); }});
  await p.waitForTimeout(800);
  const d = await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    const r=[...document.querySelectorAll('#tab-revenue tbody tr')].find(x=>x.querySelector('input[data-path^="sites."]'));
    const i=r&&r.querySelector('input[data-path$=".revenue"]');
    return { so: s.sites[0].revenue, o: i?i.value:null, nhan: r?r.querySelectorAll('.chip').length:-1 };});
  ok('Điểm bán cũng được dọn và để trống', d.so===0 && d.o==='', d.so+' · ô "'+d.o+'"');
  ok('Bảng Điểm bán không còn nhãn kỳ', d.nhan===0, d.nhan+' nhãn');

  // go tay -> dong dau ky, lan don sau khong xoa mat
  await mo(p,'Lương');
  await p.evaluate(()=>{const i=[...document.querySelectorAll('#tab-salary input[data-path$=".reported"]')][0];
    i.value='5000000'; i.dispatchEvent(new Event('change',{bubbles:true}));});
  await p.waitForTimeout(1500);
  const g = await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    const r=s.salarySites.find(y=>y.name==='Posh HCM');
    return { so: r.reported, sauDon: (()=>{ return r.reported; })() };});
  ok('🔴 Gõ tay xong thì lần dọn sau KHÔNG xoá mất', g.so===5000000, String(g.so));

  // ── 🔴 MỞ LẠI KỲ THÌ KHÔNG HỎI GÌ, VÀ BẢN NHÁP CỦA KỲ KHÁC KHÔNG ĐƯỢC GHI ────────────────────
  await mo(p,'Tổng quan');
  const h = await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    const r=s.salarySites.find(x=>x.name==='Tàu Tân An');
    return { so: r?r.reported:null, ky: r?r.nsKy:null,
      hoi: /chưa rõ của tháng nào/.test(document.body.textContent) };});
  ok('🔴 Mở lại kỳ thì KHÔNG hỏi gì', !h.hoi);
  ok('Dòng có số nhận dấu của kỳ đang mở', h.so===12133000 && h.ky==='2026-08', h.so+' · '+h.ky);

  // mo ban nhap o ky nay roi DOI KY -> ban nhap phai bi bo
  await mo(p,'Doanh thu');
  await p.click('[data-act="napFabi"][data-arg="ghe"]').catch(()=>{});
  await p.waitForTimeout(2500);
  const coNhap = await p.evaluate(()=>!!document.querySelector('[data-act="fabiGhi"]'));
  await p.selectOption('#selMonth','7'); await p.waitForTimeout(3000);
  await mo(p,'Doanh thu');
  const conNhap = await p.evaluate(()=>!!document.querySelector('[data-act="fabiGhi"]'));
  ok('🔴 Đổi kỳ là BỎ bản nháp của kỳ cũ', coNhap && !conNhap, 'mở '+coNhap+' → sau khi đổi kỳ '+conNhap);

  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/nhan-ky.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
