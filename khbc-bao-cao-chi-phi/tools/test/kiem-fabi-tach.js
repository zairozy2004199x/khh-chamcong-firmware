/* Kiểm: FABi có cơ sở MỚI thì bên này TỰ TÁCH ĐIỂM, không đứng kẹt ở "chưa ghép". */
const { chromium } = require('playwright');
const KQ={pass:[],fail:[]};
const ok=(t,c,g)=>(c?KQ.pass:KQ.fail).push(t+(g?' — '+g:''));
const moDT = async (p) => { await p.evaluate(()=>{const x=[...document.querySelectorAll('#tabs button')].find(b=>/Doanh thu/i.test(b.textContent)); x&&x.click();}); await p.waitForTimeout(700); };
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const p=await b.newPage({viewport:{width:1900,height:1000}});
  const loi=[]; p.on('pageerror',e=>loi.push(String(e))); p.on('dialog',d=>d.accept());
  await p.goto('http://127.0.0.1:8111/bao-cao-chi-phi/'); await p.waitForTimeout(800);
  if (await p.$('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(800);
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(600);
  await p.fill('#inpYear','2026'); await p.dispatchEvent('#inpYear','change'); await p.waitForTimeout(2500);
  await moDT(p);
  const diemTruoc = await p.evaluate(()=>document.querySelectorAll('input[data-path*="sites."][data-path$=".name"]').length);

  await p.click('[data-act="napFabi"]'); await p.waitForTimeout(2500);
  const d1 = await p.evaluate(()=>{
    const sel=[...document.querySelectorAll('select[data-fabi]')];
    return { so: sel.length,
      chuaGhep: sel.filter(s=>s.value==='').length,
      coTaoMoi: sel.length? [...sel[0].querySelectorAll('optgroup')].map(o=>o.label).join(' | ') : '',
      nutTaoHet: !!document.querySelector('[data-act="fabiTaoHet"]') };
  });
  ok('Đọc được 15 cửa hàng (có 2 quán mới)', d1.so===15, d1.so+'');
  ok('Ô chọn có nhóm "➕ Tạo điểm mới"', /Tạo điểm mới/.test(d1.coTaoMoi), d1.coTaoMoi);
  ok('Có nút tạo hàng loạt cho cửa hàng còn lại', d1.nutTaoHet, d1.chuaGhep+' chưa ghép');

  await p.click('[data-act="fabiTaoHet"]'); await p.waitForTimeout(1000);
  const d2 = await p.evaluate(()=>{
    const tr=[...document.querySelectorAll('select[data-fabi]')].map(s=>s.closest('tr'));
    return { tao: tr.filter(r=>/tạo điểm mới/.test(r.cells[4].textContent)).length,
      conTrong: [...document.querySelectorAll('select[data-fabi]')].filter(s=>s.value==='').length,
      nutGhi: (document.querySelector('[data-act="fabiGhi"]')||{}).textContent||'' };
  });
  ok('Bấm một nút là mọi cửa hàng còn lại có chỗ đi', d2.tao>0, d2.tao+' dòng chọn tạo mới · còn trống '+d2.conTrong);
  ok('Nút Ghi tính cả điểm sẽ tạo', /Ghi 1[0-9] điểm/.test(d2.nutGhi), d2.nutGhi.trim());

  await p.click('[data-act="fabiGhi"]'); await p.waitForTimeout(2000);
  await moDT(p);
  const d3 = await p.evaluate(()=>{
    const ten=[...document.querySelectorAll('input[data-path*="sites."][data-path$=".name"]')].map(i=>i.value);
    return { diem: ten.length,
      coMa: ten.some(t=>/Ngôi Nhà Ma/i.test(t)),
      coSnow: ten.some(t=>/^SNOW FUN/i.test(t)),
      tenCoSnow: ten.find(t=>/SNOW/i.test(t))||'',
      lienKet: document.querySelectorAll('[data-act="goLienKet"]').length };
  });
  ok('🔴 Đã tự tách điểm mới cho quán mới', d3.diem>diemTruoc, diemTruoc+' -> '+d3.diem+' điểm');
  ok('Có điểm "Ngôi Nhà Ma" (quán mới, chưa từng có điểm)', d3.coMa);
  /* ⚠️ "SNOW FUN AEON BÌNH DƯƠNG" KHÔNG phải quán mới — nó ghép vào điểm sẵn có
     "EVMN SNOW AEON MALL BÌNH DƯƠNG" (đúng như ảnh anh Thắng gửi). Đòi nó phải sinh ra một điểm
     MỚI là đòi sai: làm vậy thì mỗi tháng một điểm trùng, doanh thu tách đôi. */
  ok('"SNOW FUN" ghép vào điểm sẵn có, KHÔNG tạo trùng',
     !d3.coSnow && d3.tenCoSnow, 'điểm mang tên: '+(d3.tenCoSnow||'(khong thay)'));
  ok('Điểm mới nối luôn với FABi', d3.lienKet>=13, d3.lienKet+' điểm nối');

  // canh bao thieu ma don vi
  await p.evaluate(()=>{const x=[...document.querySelectorAll('#tabs button')].find(b=>/Kiểm tra/i.test(b.textContent)); x&&x.click();});
  await p.waitForTimeout(900);
  const kt = await p.evaluate(()=>document.querySelector('#tab-check').textContent);
  ok('Màn Kiểm tra cảnh báo điểm thiếu Mã đơn vị', /chưa có Mã đơn vị/.test(kt));

  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/fabi-tach.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
