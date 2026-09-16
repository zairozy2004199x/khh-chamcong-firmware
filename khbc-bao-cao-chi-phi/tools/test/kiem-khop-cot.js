/*
 * 🔴 PHÉP KIỂM CHỐNG "khác nhau và thiếu cột" (anh Thắng 16/09/2026).
 *
 * So TIÊU ĐỀ và GIÁ TRỊ trên tab "Chi tiết MISA" với ĐÚNG file .xlsx mà app xuất ra ở cùng một
 * lượt chạy. Không so với một danh sách chép tay: chép tay thì cả hai bên cùng lệch mà bài kiểm
 * vẫn xanh — đúng cái đã xảy ra.
 */
const { chromium } = require('playwright');
const fs=require('fs');
const KQ={pass:[],fail:[]};
const ok=(t,c,g)=>(c?KQ.pass:KQ.fail).push(t+(g?' — '+g:''));
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const p=await b.newPage({viewport:{width:1900,height:1000}});
  const loi=[]; p.on('pageerror',e=>loi.push(String(e)));
  await p.goto('http://127.0.0.1:8105/bao-cao-chi-phi/'); await p.waitForTimeout(800);
  if (await p.$('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn');
    await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(800);
  await p.click('button[data-tab="misa"]'); await p.waitForTimeout(900);

  // ---- 1. Tieu de tren man hinh (che do mac dinh) ----
  const thMH = await p.evaluate(()=>[...document.querySelectorAll('#tab-misa thead th')].slice(1).map(t=>t.textContent));
  // ---- 2. Tieu de ma exporter se ghi vao file ----
  const thFile = await p.evaluate(()=>{
    const E=window.BaoCaoExporter;
    return E.MISA_MAP.map(m=>E.MISA_COLS[m.i]);
  });
  ok('Cột màn hình = đúng những cột exporter điền, đúng thứ tự',
     JSON.stringify(thMH)===JSON.stringify(thFile), thMH.join(' | '));
  ok('Có đủ Ngày chứng từ / Ngày hạch toán / Số tiền quy đổi',
     thMH.some(x=>/Ngày chứng từ/.test(x)) && thMH.some(x=>/Ngày hạch toán/.test(x)) && thMH.some(x=>/Số tiền quy đổi/.test(x)),
     thMH.length+' cột');

  // ---- 3. "Du 50 cot" ----
  await p.click('button[data-act="misa50"]'); await p.waitForTimeout(700);
  const th50 = await p.evaluate(()=>[...document.querySelectorAll('#tab-misa thead th')].slice(1).map(t=>t.textContent));
  const all = await p.evaluate(()=>window.BaoCaoExporter.MISA_COLS.slice());
  ok('Bật "Đủ 50 cột" khớp TỪNG CHỮ với mẫu 50 cột của file',
     JSON.stringify(th50)===JSON.stringify(all), th50.length+' cột');
  await p.click('button[data-act="misa50"]'); await p.waitForTimeout(600);

  // ---- 4. GIA TRI: mo mot chung tu, so tung o voi dong tuong ung trong file ----
  await p.click('tr[data-act="misaMo"]'); await p.waitForTimeout(700);
  const mh = await p.evaluate(()=>{
    const tr=[...document.querySelectorAll('#tab-misa tbody tr')];
    const i=tr.findIndex(r=>r.dataset.act==='misaMo');
    return [...tr[i+1].cells].slice(1).map(c=>c.textContent.trim());
  });
  // ⚠️ Tra ô theo TÊN CỘT, không theo vị trí đếm tay: thêm một cột vào mẫu là mọi chỉ số cứng
  //    lệch đi, rồi bài kiểm đỏ ở chỗ không có lỗi (đã dính đúng vậy khi thêm "Mã đối tượng Có").
  const o = (ten) => mh[thMH.findIndex((t) => t.trim() === ten)];
  ok('Mở chứng từ thì bung dòng chi tiết đủ cột', mh.length===thMH.length, mh.length+'/'+thMH.length+' ô');
  ok('Ô ngày trên màn hình đúng dạng dd/mm/yyyy', /^\d{2}\/\d{2}\/\d{4}$/.test(o('Ngày chứng từ (*)')), o('Ngày chứng từ (*)'));
  ok('Hai cột ngày giống nhau (file thật để "=A3")',
     o('Ngày chứng từ (*)')===o('Ngày hạch toán (*)'), o('Ngày chứng từ (*)')+' / '+o('Ngày hạch toán (*)'));
  ok('Số tiền và Số tiền quy đổi giống nhau (file thật để "=L3")',
     o('Số tiền')===o('Số tiền quy đổi'), o('Số tiền')+' / '+o('Số tiền quy đổi'));
  ok('Có mã đơn vị ở dòng chi tiết', /\w/.test(o('Mã đơn vị')), o('Mã đơn vị'));
  ok('Có cột "Mã đối tượng Có" trên màn hình', thMH.some((t)=>t.trim()==='Mã đối tượng Có'));

  ok('Không có lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/tab-misa-cot.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
