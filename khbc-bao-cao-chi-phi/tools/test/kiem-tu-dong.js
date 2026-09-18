/* Kiểm "tự động hẳn": cơ sở MỚI bên nguồn tự nối, tự tạo điểm — không phải bấm "Nạp".
   Anh Thắng 18/09/2026: "đọc chuẩn rồi, mình tự động luôn, không cần bấm chữ nạp nữa". */
const { chromium } = require('playwright');
const KQ={pass:[],fail:[]};
const ok=(t,c,g)=>(c?KQ.pass:KQ.fail).push(t+(g?' — '+g:''));
const mo = async (p,ten) => { await p.evaluate((t)=>{const x=[...document.querySelectorAll('#tabs button')].find(b=>new RegExp(t,'i').test(b.textContent)); x&&x.click();},ten); await p.waitForTimeout(700); };
const PORT = process.env.PORT || '8118';
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const p=await b.newPage({viewport:{width:1900,height:1000}});
  const loi=[]; p.on('pageerror',e=>loi.push(String(e))); p.on('dialog',d=>d.accept());
  await p.goto('http://127.0.0.1:'+PORT+'/bao-cao-chi-phi/'); await p.waitForTimeout(800);
  if (await p.isVisible('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(800);
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(600);
  await p.fill('#inpYear','2026'); await p.dispatchEvent('#inpYear','change'); await p.waitForTimeout(2500);

  /* Chỉ nối MỘT cơ sở, bật tự lấy, và chọn sẵn bộ phận mặc định — 6 cơ sở còn lại bên Ghế là
     "cơ sở mới", phải tự vào mà không cần bấm Nạp. */
  await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    s.sites=[{dept:'posh',code:'PAMBT',name:'AEON MALL BÌNH TÂN',revenue:0,gheTen:'AEON MALL BÌNH TÂN'}];
    s.options.fabiTuDong=true; s.options.bpMacDinh={ghe:'posh'}; s.options.boQuaNguon={};
    window.BaoCaoApp.setState(s);});
  await p.waitForTimeout(2500);

  // TAI LAI TRANG — khong bam nut nao nua
  await p.reload(); await p.waitForTimeout(1200);
  if (await p.isVisible('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(6000);
  await mo(p,'Doanh thu');

  const x = await p.evaluate(()=>{const s=window.BaoCaoApp.getState(); const R=window.BaoCaoApp.getReport();
    return { diem: s.sites.length, noi: s.sites.filter(y=>(y.gheTen||'').trim()).length,
      tong: Object.values(R.revenue).reduce((a,c)=>a+c,0),
      maTrong: s.sites.filter(y=>!y.code).length,
      tt: (document.querySelector('#tab-revenue .issue')||{}).textContent||'' };});

  ok('🔴 Cơ sở mới bên nguồn TỰ VÀO, không bấm "Nạp"', x.diem===7 && x.noi===7,
     x.diem+' điểm · '+x.noi+' nối');
  ok('Doanh thu về đủ bằng tổng của nguồn', x.tong===191880000, String(x.tong));
  ok('🔴 KHÔNG bịa Mã đơn vị cho điểm tự tạo', x.maTrong===6, x.maTrong+' điểm để trống mã');
  ok('Dòng trạng thái nói rõ vừa tự tạo mấy điểm',
     /tự tạo 6 điểm mới/.test(x.tt) && /Mã đơn vị/.test(x.tt), x.tt.replace(/\s+/g,' ').slice(0,140));
  ok('Hết báo thiếu liên kết', !/THIẾU LIÊN KẾT/.test(x.tt), x.tt.replace(/\s+/g,' ').slice(0,80));

  // 🔴 khong doan duoc bo phan thi KHONG tao bua
  await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    s.sites=[]; s.options.bpMacDinh={}; window.BaoCaoApp.setState(s);});
  await p.waitForTimeout(2500);
  await p.reload(); await p.waitForTimeout(1200);
  if (await p.isVisible('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(6000);
  await mo(p,'Doanh thu');
  const y = await p.evaluate(()=>window.BaoCaoApp.getState().sites.length);
  ok('🔴 Chưa nối điểm nào thì không tự chạy (không đoán bừa bộ phận)', y===0, y+' điểm');

  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/tu-dong.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
