const { chromium } = require('playwright');
/* Dung canh anh Thang gap: 2 ma chua gan, CA HAI deu con du lieu -> bi chan sach. */
const MAY = [ { ma:'AMTP01', ten:'', coso:'' }, { ma:'AMTP02', ten:'', coso:'' } ];
const GIU = [
  { ma:'AMTP01', ly_do:'còn 237 dòng dữ liệu: nhat_ky (12) · lenh (60) · thu (112)', so_dong:237, chi_vi_du_lieu:1 },
  { ma:'AMTP02', ly_do:'còn 1 dòng dữ liệu: nhip (1)', so_dong:1, chi_vi_du_lieu:1 }
];
async function dung(){
  const b = await chromium.launch({ executablePath:'/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport:{ width:1400, height:820 } });
  await p.goto('file:///tmp/ghetest/trang.html'); await p.waitForTimeout(250);
  await p.evaluate((d) => {
    window.__LUOT = [];
    window.__TRALOI['kt_ma_misa_map'] = { ok:true, map:{} };
    window.__TRALOI['may_xoa_han'] = (q) => {
      window.__LUOT.push({ ds:q.ds, that:q.that, buoc_qua:q.buoc_qua || 0 });
      if (!q.buoc_qua) return { ok:true, xem_truoc:true, se_xoa:[], giu_lai:d.GIU, mo_coi:{}, khong_thay:[] };
      return { ok:true, da_xoa:q.ds.length, se_xoa:q.ds, giu_lai:[], thong_bao:'Đã xoá.' };
    };
    const D = { coso:[{id:1,ten:'AEON MALL TÂN PHÚ',tinh:'',ma_kh:'',dong_cua:0}], may:d.MAY,
      tong:{tong:0,theo_coso:[]}, ai:{name:'K',role:'admin'}, cho:[], choGan:[], loi:[], nhat_ky:[] };
    window.__TRALOI['so_lieu'] = D;
    window.__T.setTOK('t'); window.__T.setTAB('quan-ly'); window.__T.setD(D);
    document.getElementById('app').innerHTML = window.__T.veQuanLy();
    window.__T.noi();
  }, { MAY, GIU });
  await p.waitForTimeout(350);
  return { b, p };
}
(async () => {
  const out = {}; const loi = [];

  // --- Ca 1: GO SAI -> khong duoc xoa gi ---
  { const { b, p } = await dung();
    p.on('pageerror', e => loi.push(String(e)));
    p.on('dialog', async d => { if (d.type() === 'prompt') await d.accept('xoa'); else await d.accept(); });
    await p.click('#cs-xoa-chuagan'); await p.waitForTimeout(800);
    out.goSai = await p.evaluate(() => (window.__LUOT||[]).map(x => ({ that:x.that, buoc_qua:x.buoc_qua })));
    await b.close(); }

  // --- Ca 2: BAM HUY o o go -> khong duoc xoa gi ---
  { const { b, p } = await dung();
    p.on('dialog', async d => { if (d.type() === 'prompt') await d.dismiss(); else await d.accept(); });
    await p.click('#cs-xoa-chuagan'); await p.waitForTimeout(800);
    out.huy = await p.evaluate(() => (window.__LUOT||[]).map(x => ({ that:x.that, buoc_qua:x.buoc_qua })));
    await b.close(); }

  // --- Ca 3: GO DUNG "XOA HAN" -> moi xoa, va co co buoc_qua ---
  { const { b, p } = await dung();
    const noiDung = [];
    p.on('dialog', async d => { noiDung.push(d.message()); if (d.type() === 'prompt') await d.accept('xoa han'); else await d.accept(); });
    await p.click('#cs-xoa-chuagan'); await p.waitForTimeout(900);
    out.goDung = await p.evaluate(() => (window.__LUOT||[]).map(x => ({ ds:x.ds, that:x.that, buoc_qua:x.buoc_qua })));
    /* Giu NGUYEN VAN de kiem — cat bot roi di tim mot cau nam sau cho cat la bao hong oan. */
    out.loiNhac = noiDung;
    await b.close(); }

  console.log(JSON.stringify({ loi, ...out, loiNhac: out.loiNhac.map(t => t.slice(0, 220) + (t.length > 220 ? ' …' : '')) }, null, 1));
  const l = out.goDung;
  const hong = loi.length
    || out.goSai.some(x => x.that === 1)          /* go sai: TUYET DOI khong co luot xoa that */
    || out.huy.some(x => x.that === 1)            /* bam Huy: cung vay */
    || l.length !== 2 || l[0].that !== 0          /* luot 1 van la xem truoc */
    || l[1].that !== 1 || l[1].buoc_qua !== 1     /* luot 2 moi xoa, co co cuong che */
    || l[1].ds.join(',') !== 'AMTP01,AMTP02'
    || !out.loiNhac.some(t => t.indexOf('ĐỔI MÃ') >= 0)          /* phai goi y cach dung */
    || !out.loiNhac.some(t => t.indexOf('tổng tiền KHÔNG đổi') >= 0)
    || !out.loiNhac.some(t => t.indexOf('XOA HAN') >= 0);
  process.exit(hong ? 1 : 0);
})();
