/* Kiểm lương Mục III lấy từ trang Nhân sự — và nhất là luật KHÔNG GHI 0 cho cơ sở chưa khai giá. */
const { chromium } = require('playwright');
const KQ={pass:[],fail:[]};
const ok=(t,c,g)=>(c?KQ.pass:KQ.fail).push(t+(g?' — '+g:''));
const moLuong = async (p) => { await p.evaluate(()=>{const x=[...document.querySelectorAll('#tabs button')].find(b=>/Lương/i.test(b.textContent)); x&&x.click();}); await p.waitForTimeout(700); };
const PORT = process.env.PORT || '8113';
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const p=await b.newPage({viewport:{width:1900,height:1000}});
  const loi=[]; p.on('pageerror',e=>loi.push(String(e))); p.on('dialog',d=>d.accept());
  await p.goto('http://127.0.0.1:'+PORT+'/bao-cao-chi-phi/'); await p.waitForTimeout(800);
  if (await p.$('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(800);
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(600);
  await p.fill('#inpYear','2026'); await p.dispatchEvent('#inpYear','change'); await p.waitForTimeout(2500);
  await moLuong(p);

  ok('Mục III có nút "Nạp lương từ Nhân sự"', !!(await p.$('[data-act="napLuong"]')));
  await p.click('[data-act="napLuong"]'); await p.waitForTimeout(2500);

  const x1 = await p.evaluate(()=>{
    const tr=[...document.querySelectorAll('#tab-salary table tr')].filter(r=>/POSH MN AEON|FUNZONE CITY|TUTU MN AEON|VĂN PHÒNG HCM|JP MN AEON/.test(r.textContent));
    return { so: document.querySelectorAll('select[data-luong]').length,
      ten: tr.map(r=>r.cells[0].textContent.trim()),
      canhBao: (document.querySelector('#tab-salary .issue.warn')||{}).textContent||'',
      thang7: /KHO LẠNH THÁNG 7/.test(document.body.textContent) };
  });
  ok('Chỉ liệt kê cơ sở CÓ chấm công trong kỳ', !x1.thang7, 'không thấy cơ sở của kỳ khác');
  ok('3 cơ sở có lương được ghép (2 dạng mtd + 1 vp)', x1.so===3, x1.so+' ô chọn · thấy '+x1.ten.length+' dòng');

  // 🔴 luat quan trong nhat
  ok('🔴 Cơ sở chưa khai giá giờ KHÔNG có ô chọn để ghi', x1.so===3 && x1.ten.length===5,
     'FUNZONE + TUTU chỉ hiện lý do');
  ok('🔴 Giao diện bày LÝ DO, không bày số 0', /chưa khai giá giờ/i.test(x1.canhBao) && /không ghi số 0/i.test(x1.canhBao),
     x1.canhBao.replace(/\s+/g,' ').slice(0,130));

  // tao dong moi cho nhung co so chua ghep roi ghi
  await p.click('[data-act="luongTaoHet"]').catch(()=>{}); await p.waitForTimeout(900);
  await p.click('[data-act="luongGhi"]'); await p.waitForTimeout(2500);
  await moLuong(p);

  const x2 = await p.evaluate(()=>{
    const rows=[...document.querySelectorAll('#tab-salary tbody tr')];
    const tim=(t)=>rows.find(r=>{const i=r.querySelector('input[data-path$=".name"]'); return i && i.value===t;});
    const so=(r)=>{const i=r && r.querySelector('input[data-path$=".reported"]'); return i? i.value : null;};
    const posh=tim('POSH MN AEON MALL BÌNH DƯƠNG'), fz=tim('FUNZONE CITY VŨNG TÀU'), vp=tim('VĂN PHÒNG HCM');
    return { posh: so(posh), coPosh: !!posh, coFz: !!fz, coVp: !!vp,
      khoa: posh ? posh.querySelector('input[data-path$=".reported"]').readOnly : false,
      lienKet: rows.filter(r=>/🔗 NS/.test(r.textContent)).length,
      tt: (document.querySelector('#tab-salary .issue')||{}).textContent||'' };
  });
  ok('Ghi đúng số lương của cơ sở', String(x2.posh).replace(/\D/g,'')==='111649262', x2.posh);
  ok('🔴 Cơ sở chưa khai giá KHÔNG bị tạo dòng lương 0', !x2.coFz, x2.coFz?'đã tạo nhầm':'không tạo');
  ok('Dòng đã nối hiện 🔗 NS', x2.lienKet===2, x2.lienKet+' dòng');
  /* "VĂN PHÒNG HCM" không mang chữ hiệu bộ phận nào — KHÔNG được đoán bừa. Đoán sai một bộ phận
     là lương văn phòng chui vào chi phí của một nhóm kinh doanh và tổng vẫn cộng đẹp. */
  ok('🔴 Cơ sở không đoán được bộ phận thì KHÔNG tạo bừa', !x2.coVp, x2.coVp?'đã tạo nhầm':'để người chọn');
  ok('Bật tự lấy thì ô "Theo báo cáo" khoá lại', x2.khoa===true);
  ok('Dòng trạng thái nói rõ đang lấy từ Nhân sự', /trang Nhân sự/.test(x2.tt), x2.tt.replace(/\s+/g,' ').slice(0,110));

  // doi ky di roi ve: lien ket song keo so tu ve
  await p.selectOption('#selMonth','7'); await p.waitForTimeout(2500);
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(3500);
  await moLuong(p);
  const x3 = await p.evaluate(()=>{
    const rows=[...document.querySelectorAll('#tab-salary tbody tr')];
    const r=rows.find(x=>{const i=x.querySelector('input[data-path$=".name"]'); return i && i.value==='POSH MN AEON MALL BÌNH DƯƠNG';});
    return { so: r? r.querySelector('input[data-path$=".reported"]').value : null,
      lienKet: rows.filter(x=>/🔗 NS/.test(x.textContent)).length };
  });
  ok('Đổi kỳ đi rồi về: lương tự lấy lại', String(x3.so).replace(/\D/g,'')==='111649262' && x3.lienKet===2,
     x3.so+' · '+x3.lienKet+' dòng nối');

  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/luong-ns.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
