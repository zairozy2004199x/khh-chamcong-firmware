const { chromium } = require('playwright');
/* Dung dong AM-TP-25 trong anh anh Thang gui: dang co "Thuc thu ghi de: 340.000d". */
const C = { chairCode:'AM-TP-25', chairName:'AM-TP-25', reportId:'R1',
  meterBefore:1406, meterAfter:1488, actual:820000, cash:340000, qr:330000, paid:340000,
  note:'· Thực thu ghi đè: 340.000đ', confirmed:false, mocTay:1, anh:[], lichSu:[] };
async function ve(locked){
  const b = await chromium.launch({ executablePath:'/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport:{ width:1400, height:600 } });
  await p.goto('file:///tmp/ghetest/trang.html'); await p.waitForTimeout(200);
  await p.evaluate((d) => {
    window.__GUI = [];
    window.__TRALOI['kt_sua'] = (q) => { window.__GUI.push(q.patch); return { ok:true, message:'Xong.' }; };
    window.__T.setTOK('t');
    const tb = document.createElement('table');
    tb.innerHTML = '<tr><th>Ghế</th><th>Trước</th><th>Sau</th><th>Actual</th><th>Tiền mặt</th><th>QR</th><th>Nộp</th><th>Duyệt</th><th></th></tr>';
    tb.appendChild(window.__T.ktdRow({coso:'CS',ngay:'2026-09-14'}, d.C, document.createElement('span'), function(){}, d.locked));
    document.getElementById('app').innerHTML = ''; document.getElementById('app').appendChild(tb);
  }, { C, locked });
  await p.waitForTimeout(200);
  return { b, p };
}
(async () => {
  const out = {}; const loi = [];

  { const { b, p } = await ve(false);
    p.on('pageerror', e => loi.push(String(e)));
    out.oNhap = await p.evaluate(() => {
      /* Hàng 0 là TIÊU ĐỀ (innerHTML + appendChild nằm chung một tbody) — lấy hàng 1 mới là dữ
         liệu. Bản đầu lấy hàng 0 nên mọi ô đều "không có input" và phép kiểm báo hỏng oan. */
      const o = document.querySelectorAll('#app tr')[1];
      return [].slice.call(o.cells).map(td => ({ co: !!td.querySelector('input[type=text]'), chu: td.textContent.trim().slice(0,14) }));
    });
    // go QR moi
    await p.fill('#app tr:nth-child(2) td:nth-child(6) input', '350.000');
    await p.evaluate(() => document.querySelector('#app tr:nth-child(2) td:nth-child(6) input').blur());
    await p.waitForTimeout(250);
    // go Tien mat moi
    await p.fill('#app tr:nth-child(2) td:nth-child(5) input', '360000');
    await p.evaluate(() => document.querySelector('#app tr:nth-child(2) td:nth-child(5) input').blur());
    await p.waitForTimeout(250);
    // xoa trang Tien mat -> hoi roi go ghi de
    p.on('dialog', async d => { out.hoiGo = d.message(); await d.accept(); });
    await p.fill('#app tr:nth-child(2) td:nth-child(5) input', '');
    await p.evaluate(() => document.querySelector('#app tr:nth-child(2) td:nth-child(5) input').blur());
    await p.waitForTimeout(300);
    out.gui = await p.evaluate(() => window.__GUI);
    await b.close(); }

  { const { b, p } = await ve(true);
    out.khoa = await p.evaluate(() => document.querySelectorAll('#app input[type=text]').length);
    await b.close(); }

  console.log(JSON.stringify({ loi, ...out }, null, 1));
  const c = out.oNhap;
  const hong = loi.length
    || !c[1].co || !c[2].co            /* Truoc / Sau van la o nhap */
    || c[3].co                         /* Actual KHONG duoc cho go */
    || !c[4].co || !c[5].co            /* Tien mat + QR MOI cho go */
    || c[6].co                         /* Nop KHONG duoc cho go */
    || out.gui.length !== 3
    || out.gui[0].qr !== 350000
    || out.gui[1].actualOverride !== 360000
    || out.gui[2].bo_ghi_de !== 1
    || !out.hoiGo || out.hoiGo.indexOf('Actual − QR') < 0
    || out.khoa !== 0;                 /* khoa ngay -> khong con o nhap nao */
  process.exit(hong ? 1 : 0);
})();
