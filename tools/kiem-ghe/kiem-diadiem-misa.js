const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport: { width: 1500, height: 900 } });
  const loi = [];
  p.on('pageerror', e => loi.push('PAGEERROR: ' + e.message));
  p.on('console', m => { if (m.type() === 'error') loi.push('CONSOLE: ' + m.text()); });
  await p.goto('file://' + __dirname + '/trang.html');
  await p.waitForTimeout(400);

  const MAP = { ok:true, map:{
    'AEON MALL BÌNH DƯƠNG': { unit_id:'U001', unit_name:'AEON MALL BINH DUONG' },
    'AEON MALL TÂN PHÚ':    { unit_id:'',     unit_name:'' },
    'BV UNG BƯỚU':          { unit_id:'U003', unit_name:'BV UNG BUOU' },
    'Cơ sở chưa có ghế':    { unit_id:'',     unit_name:'' }
  }};
  await p.evaluate((MAP) => {
    window.__TRALOI['kt_ma_misa_map'] = MAP;
    window.__TRALOI['kt_ma_misa_dat'] = (d) => ({ ok:true, unit_id:d.unit_id, unit_name:d.unit_name });
    window.__T.setTOK('tok');
    window.__T.setD({
      coso: [
        { id:1, ten:'AEON MALL BÌNH DƯƠNG', tinh:'Bình Dương', ma_kh:'KH00108' },
        { id:2, ten:'AEON MALL TÂN PHÚ',    tinh:'Hồ Chí Minh', ma_kh:'KH00119' },
        { id:3, ten:'BV UNG BƯỚU',          tinh:'Hồ Chí Minh', ma_kh:'' },
        { id:4, ten:'Cơ sở chưa có ghế',    tinh:'Cần Thơ',    ma_kh:'KH00999' }
      ],
      may: [
        { ma:'80001', ten:'AM-BD-1', coso:'AEON MALL BÌNH DƯƠNG' },
        { ma:'80065', ten:'AM-TP-1', coso:'AEON MALL TÂN PHÚ' },
        { ma:'80096', ten:'CVST-8',  coso:'BV UNG BƯỚU' },
        { ma:'80999', ten:'LE-1',    coso:'' }
      ],
      tong: { tong:0, theo_coso: [] }
    });
    document.getElementById('app').innerHTML = window.__T.veQuanLy();
    window.__T.misaNap();
  }, MAP);
  await p.waitForTimeout(500);

  const r1 = await p.evaluate(() => {
    const bang = document.getElementById('cs-bang');
    const th = bang.querySelectorAll('tr:first-child th').length;
    // moi hang du lieu phai co dung so o = so cot tieu de
    const hang = [].slice.call(bang.querySelectorAll('tr')).slice(1);
    const lech = hang.map((tr, i) => tr.children.length).filter(n => n !== th);
    const oU = document.querySelectorAll('[data-misau]').length;
    const gt = {};
    [].forEach.call(document.querySelectorAll('[data-misau]'), o => gt[o.getAttribute('data-misau')] = o.value);
    const gtn = {};
    [].forEach.call(document.querySelectorAll('[data-misan]'), o => gtn[o.getAttribute('data-misan')] = o.value);
    return { soCotTieuDe: th, soHangLech: lech.length, cotLech: lech, soOUnit: oU,
             giaTriUnit: gt, giaTriTen: gtn,
             demThieu: document.getElementById('cs-misa-thieu').textContent.trim(),
             goiDaGui: (window.__GOI||[]).map(g => g.viec) };
  });

  // ---- Sua mot o -> phai tu luu, va gui DUNG ten co so ----
  await p.fill('[data-misau="AEON MALL TÂN PHÚ"]', 'U002');
  await p.evaluate(() => document.querySelector('[data-misau="AEON MALL TÂN PHÚ"]').blur());
  await p.waitForTimeout(300);
  const r2 = await p.evaluate(() => {
    const g = (window.__GOI||[]).filter(x => x.viec === 'kt_ma_misa_dat').pop();
    const o = document.querySelector('[data-misau="AEON MALL TÂN PHÚ"]');
    return { guiLen: g ? g.d : null, vien: o.style.outline,
             demThieu: document.getElementById('cs-misa-thieu').textContent.trim() };
  });

  // ---- Nhanh HONG: may chu tu choi -> vien PHAI do va GIU chu vua go ----
  await p.evaluate(() => { window.__TRALOI['kt_ma_misa_dat'] = { ok:false, message:'Thiếu tên cơ sở.' }; });
  p.once('dialog', d => d.accept());
  await p.fill('[data-misau="BV UNG BƯỚU"]', 'SAI');
  await p.evaluate(() => document.querySelector('[data-misau="BV UNG BƯỚU"]').blur());
  await p.waitForTimeout(400);
  const r3 = await p.evaluate(() => {
    const o = document.querySelector('[data-misau="BV UNG BƯỚU"]');
    return { giuChu: o.value, vienDo: o.style.outline.indexOf('220, 38, 38') >= 0 || o.style.outline.indexOf('#dc2626') >= 0 };
  });

  // ---- Khong du quyen doc bang MISA -> AN HAN hai cot ----
  await p.evaluate(() => {
    window.__TRALOI['kt_ma_misa_map'] = { ok:false, ma:'khong_du_quyen' };
    document.getElementById('app').innerHTML = window.__T.veQuanLy();
    window.__T.misaNap();
  });
  await p.waitForTimeout(300);
  const r4 = await p.evaluate(() => {
    const c = document.querySelectorAll('.misa-col');
    let hien = 0; [].forEach.call(c, e => { if (getComputedStyle(e).display !== 'none') hien++; });
    return { tongOMisa: c.length, conHien: hien };
  });

  await p.screenshot({ path: '/tmp/ghetest/xem.png', fullPage: false });
  console.log(JSON.stringify({ loi, r1, r2, r3, r4 }, null, 1));
  await b.close();
  const hong = loi.length || r1.soHangLech || r1.soCotTieuDe !== 6 || r1.soOUnit !== 4
    || r1.giaTriUnit['AEON MALL BÌNH DƯƠNG'] !== 'U001'
    || r1.giaTriTen['BV UNG BƯỚU'] !== 'BV UNG BUOU'
    || r1.demThieu.indexOf('2') < 0
    || !r2.guiLen || r2.guiLen.coso !== 'AEON MALL TÂN PHÚ' || r2.guiLen.unit_id !== 'U002'
    || r2.demThieu.indexOf('1') < 0
    || r3.giuChu !== 'SAI' || !r3.vienDo
    || r4.conHien !== 0;
  process.exit(hong ? 1 : 0);
})();
