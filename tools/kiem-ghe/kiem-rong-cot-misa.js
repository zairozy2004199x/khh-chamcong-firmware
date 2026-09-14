const { chromium } = require('playwright');
const TEN = ['AEON MALL BÌNH DƯƠNG','AEON MALL BÌNH TÂN','AEON MALL TÂN PHÚ','BỆNH VIỆN 175','BV UNG BƯỚU','CGV LANDMARK 81'];
(async () => {
  const b = await chromium.launch({ executablePath:'/opt/pw-browsers/chromium' });
  const out = {};
  for (const W of [1500, 1280, 1024]) {
    const p = await b.newPage({ viewport:{ width:W, height:800 } });
    await p.goto('file://' + __dirname + '/trang.html'); await p.waitForTimeout(250);
    await p.evaluate((TEN) => {
      const map = {}; TEN.forEach((t,i) => map[t] = { unit_id:'U'+(i+1), unit_name:t });
      window.__TRALOI['kt_ma_misa_map'] = { ok:true, map };
      window.__T.setTOK('t');
      window.__T.setD({
        coso: TEN.map((t,i) => ({ id:i+1, ten:t, tinh:'Hồ Chí Minh', ma_kh:'KH0011'+i })),
        may: TEN.map((t,i) => ({ ma:'800'+(10+i), ten:'X-'+i, coso:t })),
        tong:{ tong:0, theo_coso:[] } });
      document.getElementById('app').innerHTML = window.__T.veQuanLy();
      window.__T.misaNap();
    }, TEN);
    await p.waitForTimeout(400);
    out[W] = await p.evaluate(() => {
      const r = [];
      document.querySelectorAll('[data-misan]').forEach(o => {
        // chu co bi cat khong: scrollWidth > clientWidth nghia la con chu bi khuat
        r.push({ ten:o.value, rong:Math.round(o.getBoundingClientRect().width),
                 biCat: o.scrollWidth > o.clientWidth + 1 });
      });
      return { o:r, tranNgang: document.documentElement.scrollWidth > window.innerWidth + 2,
               scrollW: document.documentElement.scrollWidth };
    });
    if (W === 1500) await p.screenshot({ path:'/tmp/ghetest/rong.png',
      clip: await p.evaluate(() => { const e=document.getElementById('cs-bang'); const b=e.getBoundingClientRect();
        return {x:Math.max(0,b.x-6), y:Math.max(0,b.y-6), width:Math.min(b.width+12, innerWidth), height:Math.min(b.height+12, 700)}; }) });
    await p.close();
  }
  console.log(JSON.stringify(out, null, 1));
  await b.close();
  const cat = Object.values(out).some(v => v.o.some(x => x.biCat));
  process.exit(cat ? 1 : 0);
})();
