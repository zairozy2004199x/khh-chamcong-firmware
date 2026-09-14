const { chromium } = require('playwright');
/* 3 ma chua gan: 2 sach (xoa duoc), 1 con doanh thu (PHAI giu).
   1 ma chua gan nhung DA AN -> khong hien o hang "(chua gan)", khong duoc dua vao lenh xoa.
   1 ma DANG THUOC co so -> tuyet doi khong duoc dinh vao. */
const MAY = [
  { ma:'AMBT01', ten:'', coso:'' },
  { ma:'AMBT02', ten:'', coso:'' },
  { ma:'80840',  ten:'', coso:'' },
  { ma:'ANDA99', ten:'', coso:'', an:1 },
  { ma:'AM-BD-1',ten:'AM-BD-1', coso:'AEON MALL BÌNH DƯƠNG' }
];
(async () => {
  const b = await chromium.launch({ executablePath:'/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport:{ width:1500, height:900 } });
  const loi = []; p.on('pageerror', e => loi.push('PAGEERROR: ' + e.message));
  await p.goto('file:///tmp/ghetest/trang.html'); await p.waitForTimeout(250);

  await p.evaluate((MAY) => {
    window.__TRALOI['kt_ma_misa_map'] = { ok:true, map:{} };
    /* May chu: 80840 con doanh thu -> giu lai; hai ma kia sach. */
    window.__TRALOI['may_xoa_han'] = (d) => {
      window.__LUOT = window.__LUOT || []; window.__LUOT.push({ ds:d.ds, that:d.that });
      const giu = [{ ma:'80840', ly_do:'còn 7 dòng dữ liệu: bc_dong (5) · thu (2)' }];
      const xoa = (d.ds||[]).filter(m => m !== '80840');
      return d.that ? { ok:true, da_xoa:xoa.length, se_xoa:xoa, giu_lai:giu, thong_bao:'Đã xoá.' }
                    : { ok:true, xem_truoc:true, se_xoa:xoa, giu_lai:giu, khong_thay:[], so_bang_da_do:9 };
    };
    window.__TRALOI['so_lieu'] = { ok:true, coso:[{id:1,ten:'AEON MALL BÌNH DƯƠNG',tinh:'',ma_kh:'',dong_cua:0}],
      may:MAY, tong:{tong:0,theo_coso:[]}, ai:{name:'K',role:'admin'}, cho:[], choGan:[], loi:[], nhat_ky:[] };
    window.__T.setTOK('t'); window.__T.setTAB('quan-ly');
    window.__T.setD({ coso:[{id:1,ten:'AEON MALL BÌNH DƯƠNG',tinh:'',ma_kh:'',dong_cua:0}],
      may:MAY, tong:{tong:0,theo_coso:[]}, ai:{name:'K',role:'admin'}, cho:[], choGan:[], loi:[], nhat_ky:[] });
    document.getElementById('app').innerHTML = window.__T.veQuanLy();
    window.__T.noi();
  }, MAY);
  await p.waitForTimeout(400);

  const coNut = await p.evaluate(() => !!document.getElementById('cs-xoa-chuagan'));
  const hoi = [];
  p.on('dialog', async d => { hoi.push(d.message()); await d.accept(); });
  await p.click('#cs-xoa-chuagan');
  await p.waitForTimeout(600);

  const r = await p.evaluate(() => ({ luot: window.__LUOT || [] }));

  await p.screenshot({ path:'/tmp/ghetest/xoama.png', fullPage:false });
  console.log(JSON.stringify({ loi, coNut, soLuot:r.luot.length, luot:r.luot, hoi:hoi.map(h=>h.slice(0,260)) }, null, 1));
  await b.close();

  const l = r.luot;
  const hong = loi.length || !coNut
    || l.length !== 2                                   /* xem truoc, roi moi xoa that */
    || l[0].that !== 0                                  /* luot 1 KHONG duoc xoa gi */
    || l[1].that !== 1
    || l[0].ds.length !== 3                             /* 3 ma chua gan dang hien */
    || l[0].ds.indexOf('ANDA99') >= 0                   /* ma DA AN khong duoc dinh vao */
    || l[0].ds.indexOf('AM-BD-1') >= 0                  /* ma DANG THUOC co so khong duoc dinh vao */
    || l[1].ds.indexOf('80840') >= 0                    /* ma con du lieu KHONG duoc gui di xoa */
    || l[1].ds.length !== 2
    || hoi.length < 1
    || hoi[0].indexOf('AMBT01') < 0                     /* cau hoi phai LIET KE ma sap mat */
    || hoi[0].indexOf('80840') < 0;                     /* va noi ro ma nao duoc GIU va vi sao */
  process.exit(hong ? 1 : 0);
})();
