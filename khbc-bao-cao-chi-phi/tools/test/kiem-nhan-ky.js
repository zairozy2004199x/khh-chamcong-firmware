/* Kiểm "nhìn là biết số nào của kỳ này": nhãn kỳ trên từng dòng + nút đưa số kỳ trước về 0.
   Anh Thắng 18/09/2026: "nó như này chả biết dữ liệu nào thật, dữ liệu nào giả". */
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

  // dung dung canh trong anh: ky T09, mot dong so ky nay + mot dong so ky truoc
  await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    /* Đứng nguyên ở kỳ T08 (kỳ máy chủ đang có) để không kích hoạt hộp "dựng kỳ mới". */
    s.period={month:8,year:2026}; s.soCuaKy='2026-08';
    s.salarySites=[
      {id:'a',dept:'posh',name:'Posh HCM',reported:111649262,report:111649262,dntt:111649262,actual:111649262,nsKy:'2026-07'},
      {id:'b',dept:'jp',name:'BP JP HCM',reported:41635755,report:41635755,dntt:41635755,actual:41435755,nsKy:'2026-08'},
    ];
    s.sites=[{dept:'tutu',code:'TTAMTP',name:'AMTP',revenue:29905000,dtKy:'2026-07'}];
    window.BaoCaoApp.setState(s);});
  await p.waitForTimeout(1500);
  await mo(p,'Lương');

  const x = await p.evaluate(()=>{
    const rows=[...document.querySelectorAll('#tab-salary tbody tr')];
    const tim=(t)=>rows.find(r=>{const i=r.querySelector('input[data-path$=".name"]'); return i && i.value===t;});
    const nhan=(r)=>{const c=r&&r.querySelector('.chip'); return c?c.textContent.trim():'';};
    return { posh: nhan(tim('Posh HCM')), jp: nhan(tim('BP JP HCM')) };});
  ok('🔴 Dòng mang số kỳ trước có nhãn ĐỎ ghi rõ kỳ nào', /2026-07/.test(x.posh), 'Posh HCM: "'+x.posh+'"');
  ok('Dòng số của kỳ này có nhãn "kỳ này"', /kỳ này/.test(x.jp), 'BP JP HCM: "'+x.jp+'"');

  await mo(p,'Doanh thu');
  /* Bộ lọc bộ phận được nhớ trong localStorage — đặt lại "Tất cả" kẻo điểm cần soi bị ẩn. */
  await p.evaluate(()=>{const f=document.querySelector('#siteFilter');
    if(f){ f.value='all'; f.dispatchEvent(new Event('change',{bubbles:true})); }});
  await p.waitForTimeout(800);
  /* #tab-revenue có nhiều bảng — bám đúng hàng của BẢNG ĐIỂM BÁN (hàng có input "sites.N.…"). */
  const d = await p.evaluate(()=>{const r=[...document.querySelectorAll('#tab-revenue tbody tr')]
    .find(x=>x.querySelector('input[data-path^="sites."]'));
    const c=r&&r.querySelector('.chip'); return c?c.textContent.trim():'';});
  ok('Bảng Điểm bán cũng có nhãn kỳ', /2026-07/.test(d), '"'+d+'"');

  await mo(p,'Tổng quan');
  const t = await p.evaluate(()=>{const e=[...document.querySelectorAll('#tab-dashboard .issue')]
    .find(x=>/SỐ KỲ TRƯỚC/.test(x.textContent));
    return { co: !!e, chu: e?e.textContent.replace(/\s+/g,' '):'',
      nut: e?[...e.querySelectorAll('button')].map(b=>b.dataset.act):[] };});
  ok('Tổng quan báo có bao nhiêu dòng mang số kỳ trước', t.co && /2 dòng/.test(t.chu), t.chu.slice(0,120));
  ok('Có nút đưa về 0', t.nut.includes('xoaSoCu'), t.nut.join(' · '));

  await p.click('#tab-dashboard [data-act="xoaSoCu"]'); await p.waitForTimeout(2000);
  const z = await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    const r=s.salarySites[0];
    return { bon: [r.reported,r.report,r.dntt,r.actual], dt: s.sites[0].revenue,
      giu: s.salarySites[1].reported, ten: r.name, bp: r.dept,
      conBao: /SỐ KỲ TRƯỚC/.test(document.querySelector('#tab-dashboard').textContent) };});
  ok('🔴 Đưa về 0 là về CẢ BỐN CỘT', z.bon.every(v=>v===0), JSON.stringify(z.bon));
  ok('Điểm bán mang số kỳ trước cũng về 0', z.dt===0, String(z.dt));
  ok('🔴 KHÔNG đụng vào dòng có số của kỳ này', z.giu===41635755, String(z.giu));
  ok('Giữ nguyên danh mục (tên, bộ phận)', z.ten==='Posh HCM' && z.bp==='posh', z.ten+' · '+z.bp);
  ok('Xong thì hết báo', !z.conBao);

  // go tay mot so -> dong dau ky, khong bi coi la so cu nua
  await mo(p,'Lương');
  await p.evaluate(()=>{const i=[...document.querySelectorAll('#tab-salary input[data-path$=".reported"]')][0];
    i.value='5000000'; i.dispatchEvent(new Event('change',{bubbles:true}));});
  await p.waitForTimeout(1500);
  const g = await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    const r=s.salarySites.find(x=>x.name==='Posh HCM');
    return { ky: r.nsKy, so: r.reported };});
  ok('🔴 Gõ tay thì đóng dấu kỳ, lần lấy sau không xoá mất', g.ky==='2026-08' && g.so===5000000,
     g.so+' · '+g.ky);

  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/nhan-ky.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
