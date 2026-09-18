/* Kiểm lương Mục III lấy từ trang Nhân sự qua VHCC_BangLuong::dung().
   Hai luật quan trọng nhất: KHÔNG ghi 0 giả, và KHÔNG cộng đôi cơ sở phụ. */
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
  if (await p.isVisible('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(800);
  await p.selectOption('#selMonth','8'); await p.waitForTimeout(600);
  await p.fill('#inpYear','2026'); await p.dispatchEvent('#inpYear','change'); await p.waitForTimeout(2500);
  await moLuong(p);

  ok('Mục III có nút "Nạp lương từ Nhân sự"', !!(await p.$('[data-act="napLuong"]')));
  await p.click('[data-act="napLuong"]'); await p.waitForTimeout(3000);

  const x = await p.evaluate(()=>{
    const rows=[...document.querySelectorAll('#tab-salary table tr')];
    const tim=(t)=>rows.find(r=>new RegExp(t.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')).test(r.cells[0]?r.cells[0].textContent:''));
    const so=(r)=>r? r.cells[1].textContent.trim() : null;
    return { oChon: document.querySelectorAll('select[data-luong]').length,
      fz: so(tim('FZ_SC_VIVO_T4')), coFz: !!tim('FZ_SC_VIVO_T4'),
      jp: so(tim('JP MN AEON MALL BÌNH TÂN')),
      posh: so(tim('POSH MN AEON MALL BÌNH DƯƠNG')),
      ev: so(tim('EVENT VR TÂN AN')),
      vpChon: (()=>{const r=[...document.querySelectorAll('#tab-salary table tr')].find(x=>/VĂN PHÒNG HCM/.test(x.textContent));
        const sl=r&&r.querySelector('select'); return sl? sl.value : '?';})(),
      vp: so(tim('VĂN PHÒNG HCM')),
      daChon: [...document.querySelectorAll('select[data-luong]')].filter(s=>s.value!=='').length,
      tongChon: document.querySelectorAll('select[data-luong]').length,
      coPhu: !!tim('FZ_SC_VIVO_PHU'),
      tutu: tim('TUTU MN AEON MALL TÂN PHÚ') ? tim('TUTU MN AEON MALL TÂN PHÚ').textContent.replace(/\s+/g,' ') : '',
      thang7: /KHO LẠNH THÁNG 7/.test(document.body.textContent),
      canhBao: (document.querySelector('#tab-salary .issue.warn')||{}).textContent||'' };});

  // 🔴 dung cai anh Thang bao: Khu vui choi CO luong that
  ok('🔴 Khu vui chơi CÓ lương thì phải ra đúng số, không còn "chưa khai giá giờ"',
     x.coFz && x.fz.replace(/\D/g,'')==='52287040', 'FZ_SC_VIVO_T4 = '+x.fz);

  // 🔴 Posh / JP la MAY TU DONG — VHCC_BangLuong::dung() KHONG tinh duoc cho nhom nay
  ok('🔴 Posh (Máy tự động) tính bằng lõi riêng, không báo "chưa khai đơn giá"',
     x.posh && x.posh.replace(/\D/g,'')==='111649262', 'Posh = '+x.posh);
  ok('🔴 Văn phòng cũng có lõi riêng', x.vp && x.vp.replace(/\D/g,'')==='88000000', 'VP = '+x.vp);

  // 🔴 co so phu ghep vao co so chinh -> khong duoc cong doi
  ok('🔴 Cơ sở PHỤ ghép vào cơ sở chính thì KHÔNG liệt kê (tránh cộng đôi lương)',
     !x.coPhu, x.coPhu?'đã cộng đôi':'đã bỏ qua');

  // TOTAL SALARY = luong chinh + cong - tru  (41.435.755 + 500.000 - 300.000)
  /* Khoản cộng / trừ chỉ có ở lối 'tho' (VHCC_BangLuong::dung): 10.000.000 + 500.000 − 300.000. */
  ok('Cộng đúng công thức TOTAL SALARY (lương chính + cộng − trừ)',
     x.ev && x.ev.replace(/\D/g,'')==='10200000', 'Event VR Tân An = '+x.ev);
  ok('Máy tự động lấy đúng tổng của lõi MTĐ (không có cộng/trừ)',
     x.jp.replace(/\D/g,'')==='41435755', 'JP = '+x.jp);

  // 🔴 chua khai don gia -> tong 0 -> BAO CHUA CO, khong ghi 0
  ok('🔴 Chưa khai đơn giá thì báo CHƯA CÓ, không ghi số 0',
     /chưa khai đơn giá/i.test(x.tutu), x.tutu.slice(0,120));
  ok('Giao diện bày LÝ DO, không bày số 0',
     /không ghi số 0/i.test(x.canhBao), x.canhBao.replace(/\s+/g,' ').slice(0,120));
  ok('Chỉ liệt kê cơ sở CÓ chấm công trong kỳ', !x.thang7, 'không thấy cơ sở của kỳ khác');

  // 🔴 "+ nap": nap xong la ghep het luon, khong phai chon tay
  /* "Văn phòng HCM" không mang chữ hiệu bộ phận nào, mà lương văn phòng vốn thuộc Mục II chứ
     không phải Mục III — nên nó ĐỨNG NGOÀI, để người chọn. Mọi cơ sở còn lại ghép sẵn hết. */
  ok('🔴 Nạp xong là ghép hết những cơ sở đoán được, khỏi chọn tay',
     x.daChon===x.tongChon-1 && x.tongChon>=5, x.daChon+'/'+x.tongChon+' cơ sở đã chọn sẵn');
  ok('🔴 Cơ sở không đoán được bộ phận thì để yên, không nhét bừa', x.vpChon==='', 'VP = "'+x.vpChon+'"');

  await p.click('[data-act="luongGhi"]'); await p.waitForTimeout(3000);
  await moLuong(p);
  const g = await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    const r=s.salarySites.find(x=>/FZ_SC_VIVO_T4/.test(x.nsTen||''));
    return { fz: r?r.reported:null, actual: r?r.actual:null,
      coPhu: s.salarySites.some(x=>/FZ_SC_VIVO_PHU/.test(x.nsTen||'')),
      co0: s.salarySites.some(x=>/TUTU/.test(x.nsTen||'')) };});
  ok('Ghi đúng số vào Mục III', g.fz===52287040 && g.actual===52287040, String(g.fz));
  ok('🔴 Không tạo dòng lương cho cơ sở chưa khai đơn giá', !g.co0);
  ok('🔴 Không tạo dòng cho cơ sở phụ', !g.coPhu);

  // 🔧 chan doan + 🔍 kham
  await p.click('[data-act="napLuong"]'); await p.waitForTimeout(3000);
  await p.evaluate(()=>{const tr=[...document.querySelectorAll('#tab-salary table tr')].find(r=>/TUTU MN AEON/.test(r.textContent));
    const b=tr&&tr.querySelector('[data-act="nsChuanDoan"]'); b&&b.click();});
  await p.waitForTimeout(2500);
  const c = await p.evaluate(()=>({ hien: !document.querySelector('#logModal').hidden,
    than: (document.querySelector('#logBody')||{}).textContent||'' }));
  ok('🔧 Chẩn đoán soi CẢ hàm đang dùng lẫn hàm cũ',
     c.hien && /VHCC_BangLuong::dung/.test(c.than) && /bang_cong_va_luong/.test(c.than),
     c.than.replace(/\s+/g,' ').slice(0,130));
  ok('🔴 Chẩn đoán KHÔNG in họ tên / CCCD',
     !/NGUYỄN VĂN A/.test(c.than) && /\(chuỗi\)/.test(c.than), 'đã giấu');

  await p.evaluate(()=>{document.querySelector('#logModal').hidden=true;});
  await p.waitForTimeout(400);
  await p.click('[data-act="nsKham"]'); await p.waitForTimeout(2500);
  const k = await p.evaluate(()=>({ hien: !document.querySelector('#logModal').hidden,
    than: (document.querySelector('#logBody')||{}).textContent||'' }));
  ok('🔍 Khám liệt kê được lớp / hàm của plugin Chấm công',
     k.hien && /VHCC_BangLuong/.test(k.than) && /dung/.test(k.than), k.than.replace(/\s+/g,' ').slice(0,110));
  ok('🔴 Khám chỉ in TÊN, không đọc nội dung bảng', /Không đọc nội dung bảng nào/.test(k.than));

  // ── 🔴 SANG KỲ MỚI CHƯA CÓ SỐ → VỀ 0 (anh Thắng: "sang tháng mới nếu chưa có số liệu cho về 0")
  await p.evaluate(()=>{document.querySelector('#logModal').hidden=true;});
  await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    /* Dựng đúng cảnh trong ảnh: dòng lương mang số của kỳ TRƯỚC, kỳ này nguồn không có. */
    s.salarySites=[{id:'z',dept:'posh',name:'Posh HCM',reported:111649262,actual:111649262,
      nsTen:'KHÔNG CÒN BÊN NHÂN SỰ',nsKy:'2026-07'}];
    s.options.luongTuDong=true; window.BaoCaoApp.setState(s);});
  await p.waitForTimeout(2500);
  /* Tải lại trang: "tự lấy" chạy ngay khi mở trang (1.19.0), đó là lúc luật về-0 làm việc. */
  await p.reload(); await p.waitForTimeout(1200);
  if (await p.isVisible('#gatePin')) { await p.fill('#gatePin','1111'); await p.click('#gateBtn'); await p.waitForSelector('#gate',{state:'hidden',timeout:15000}); }
  await p.waitForTimeout(5000);
  await moLuong(p);
  const z = await p.evaluate(()=>{const s=window.BaoCaoApp.getState();
    return { so: s.salarySites[0].reported, tt: (document.querySelector('#tab-salary .issue')||{}).textContent||'' };});
  ok('🔴 Số của kỳ TRƯỚC mà kỳ này chưa có số liệu → VỀ 0', z.so===0, 'còn '+z.so);
  ok('Nói rõ đã đưa bao nhiêu dòng về 0 và vì sao', /đưa về 0/.test(z.tt) && /kỳ trước/.test(z.tt),
     z.tt.replace(/\s+/g,' ').slice(0,130));

  ok('Không lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/luong-ns.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
