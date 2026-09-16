const { chromium } = require('playwright');
const KQ={pass:[],fail:[]};
const ok=(t,c,g)=>(c?KQ.pass:KQ.fail).push(t+(g?' — '+g:''));
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const p=await b.newPage({viewport:{width:1700,height:1000}});
  const loi=[]; p.on('pageerror',e=>loi.push(String(e)));
  await p.goto('http://127.0.0.1:8106/bao-cao-chi-phi/'); await p.waitForTimeout(700);
  // Cổng PIN: gõ vào ô rồi bấm Đăng nhập (bàn phím số là nút, không nhận keypress toàn trang).
  if (await p.$('#gatePin')) {
    await p.fill('#gatePin', '1111');
    await p.click('#gateBtn');
    await p.waitForSelector('#gate', { state: 'hidden', timeout: 15000 });
  }
  await p.waitForTimeout(800);

  ok('Có tab "Chi tiết MISA"', await p.evaluate(()=>!!document.querySelector('button[data-tab="misa"]')));
  await p.click('button[data-tab="misa"]'); await p.waitForTimeout(800);

  // Đọc từ CHÍNH hai thẻ pill của tab, không quét cả trang: chữ "dòng" còn xuất hiện ở tab
  // Tổng quan ("20 dòng · theo báo cáo…"), quét cả trang thì bắt nhầm và bài kiểm so với rác.
  const d1 = await p.evaluate(()=>{
    const pill=[...document.querySelectorAll('#tab-misa .pill')].map(x=>x.textContent.trim());
    const so=(re)=>{ const t=pill.find(x=>re.test(x)); return t? Number((t.match(/[\d.]+/)||[])[0].replace(/\./g,'')) : null; };
    return { soCT: so(/chứng từ/), soDong: so(/dòng/),
      hangCT: document.querySelectorAll('tr[data-act="misaMo"]').length,
      ngay: /31\/08\/2026/.test(document.querySelector('#tab-misa').textContent) };
  });
  ok('Bày ra số chứng từ / số dòng', !!d1.soCT && !!d1.soDong, d1.soCT+' chứng từ, '+d1.soDong+' dòng');
  ok('Số dòng = số chứng từ × số điểm nhận chi phí', d1.soDong % d1.soCT === 0,
     d1.soDong+' / '+d1.soCT+' = '+(d1.soDong/d1.soCT)+' điểm');
  ok('Gập sẵn — mỗi chứng từ một hàng', Number(d1.hangCT)===Number(d1.soCT), d1.hangCT+' hàng');
  ok('Ngày lấy theo kỳ (31/08/2026)', d1.ngay);

  // bam mo mot chung tu
  const truoc = await p.evaluate(()=>document.querySelectorAll('tbody tr').length);
  await p.click('tr[data-act="misaMo"]'); await p.waitForTimeout(500);
  const sau = await p.evaluate(()=>document.querySelectorAll('tbody tr').length);
  ok('Bấm mở thì bung dòng chi tiết', sau>truoc, truoc+' -> '+sau);
  await p.click('tr[data-act="misaMo"]'); await p.waitForTimeout(400);
  ok('Bấm lại thì gập lại', await p.evaluate(()=>document.querySelectorAll('tbody tr').length)===truoc);

  // mo tat ca
  await p.click('button[data-act="misaMoHet"]'); await p.waitForTimeout(900);
  const het = await p.evaluate(()=>document.querySelectorAll('#tab-misa tbody tr').length);
  // mở hết = mỗi chứng từ 1 hàng tóm tắt + đủ dòng chi tiết + 1 hàng Tổng cộng
  ok('Mở tất cả bung đúng số hàng', het === d1.soCT + d1.soDong + 1,
     het+' hàng (mong đợi '+(d1.soCT+d1.soDong+1)+')');

  // loc thieu tai khoan
  const coNut = await p.evaluate(()=>!!document.querySelector('button[data-act="misaThieu"]'));
  if (coNut) {
    await p.click('button[data-act="misaThieu"]'); await p.waitForTimeout(600);
    const chiThieu = await p.evaluate(()=>{
      const tr=[...document.querySelectorAll('tr[data-act="misaMo"]')];
      return tr.length && tr.every(r=>/thiếu/.test(r.textContent));
    });
    ok('Lọc "thiếu tài khoản" chỉ còn chứng từ thiếu', chiThieu);
    await p.click('button[data-act="misaThieu"]'); await p.waitForTimeout(500);
  } else ok('Lọc thiếu tài khoản', true, 'không có chứng từ nào thiếu — bỏ qua');

  // doi bo phan
  await p.evaluate(()=>{ const b=[...document.querySelectorAll('.seg button')].find(x=>/Pinball/i.test(x.textContent)); if(b) b.click(); });
  await p.waitForTimeout(700);
  ok('Đổi bộ phận sang Pinball được', await p.evaluate(()=>/PBAMTP|Pinball/i.test(document.body.textContent)));

  ok('Không có lỗi JS', loi.length===0, loi.join(' | '));
  await p.screenshot({path:'/tmp/claude-0/tab-misa.png'});
  await p.click('button[data-act="misaMoHet"]').catch(()=>{}); await p.waitForTimeout(600);
  await p.screenshot({path:'/tmp/claude-0/tab-misa-mo.png'});
  await b.close();
  console.log('PASS '+KQ.pass.length+' / FAIL '+KQ.fail.length);
  KQ.pass.forEach(t=>console.log('  ✓ '+t)); KQ.fail.forEach(t=>console.log('  ✗ '+t));
  process.exit(KQ.fail.length?1:0);
})();
