/* Kiểm thử nút Thoát trong hộp "Tài khoản của bạn" */
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url'; import { dirname } from 'node:path';
const SP=dirname(fileURLToPath(import.meta.url));
let fail=0;
const t=(n,g,w)=>{const ok=JSON.stringify(g)===JSON.stringify(w);if(!ok)fail++;
  console.log(`${ok?'PASS':'FAIL'}  ${n.padEnd(52)} got=${JSON.stringify(g)}${ok?'':' want='+JSON.stringify(w)}`)};

const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell'});
const ctx=await b.newContext({viewport:{width:1280,height:900}});
const OUT='https://khmatrix.com/?khh_thoat=1&_wpnonce=abc123';
await ctx.addInitScript(o=>{window.KH_API={rest:'/x/',nonce:'n',me:'Nguyễn Thị Minh Thư',
  title:'Khu vui chơi',role:'staff',login:'mnnv2kvc0008',out:o}},OUT);
const p=await ctx.newPage();
const errs=[];p.on('pageerror',e=>errs.push('PAGEERROR: '+e.message));
await p.goto('file://'+SP+'/build/index.html'); await p.waitForTimeout(1300);

await p.click('[data-me]').catch(async()=>{ await p.evaluate(()=>window.APP.meDialog&&window.APP.meDialog()); });
await p.waitForTimeout(400);
let co=await p.evaluate(()=>!!document.querySelector('dialog[open]'));
if(!co){ /* mở bằng nút người dùng ở thanh trên */
  await p.evaluate(()=>{const b=[...document.querySelectorAll('button')].find(x=>/Minh Thư/.test(x.textContent));if(b)b.click()});
  await p.waitForTimeout(400);
  co=await p.evaluate(()=>!!document.querySelector('dialog[open]'));
}
t('mở được hộp Tài khoản của bạn', co, true);
t('hộp có tiêu đề đúng', await p.evaluate(()=>document.querySelector('dialog h3').innerText.trim()), 'Tài khoản của bạn');
t('hiện tên đăng nhập', await p.evaluate(()=>document.querySelector('dialog').innerText.includes('mnnv2kvc0008')), true);
const nut=await p.evaluate(()=>{const b=[...document.querySelectorAll('dialog .dlg-f button')].find(x=>x.innerText.trim()==='Thoát');return !!b});
t('có nút Thoát', nut, true);

/* bấm Thoát rồi bỏ xác nhận thì không đi đâu */
p.once('dialog',d=>d.dismiss());
await p.evaluate(()=>[...document.querySelectorAll('dialog .dlg-f button')].find(x=>x.innerText.trim()==='Thoát').click());
await p.waitForTimeout(400);
t('huỷ xác nhận thì ở nguyên trang', p.url().includes('index.html'), true);
t('  hộp vẫn mở', await p.evaluate(()=>!!document.querySelector('dialog[open]')), true);

/* đồng ý thì đi tới đúng đường dẫn thoát có mã chống giả mạo */
let di='';
await p.route('**/*', r=>{ di=r.request().url(); r.abort(); });
p.once('dialog',d=>d.accept());
await p.evaluate(()=>[...document.querySelectorAll('dialog .dlg-f button')].find(x=>x.innerText.trim()==='Thoát').click());
await p.waitForTimeout(900);
t('đồng ý thì đi tới đường dẫn thoát', di, OUT);
t('  đường dẫn có mã chống giả mạo', di.includes('_wpnonce='), true);

await b.close();
console.log(errs.length?'\nLỗi trang:\n'+errs.join('\n'):'\nKhông có lỗi JS');
console.log(fail?`\n${fail} phép thử KHÔNG đạt`:'\nTất cả phép thử đều đạt');
process.exit(fail||errs.length?1:0);
