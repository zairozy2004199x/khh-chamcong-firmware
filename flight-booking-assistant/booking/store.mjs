// Kho đơn hàng — ghi ra một file JSON, ghi kiểu atomic (ghi file tạm rồi đổi tên)
// nên máy chủ tắt giữa chừng cũng không để lại file đơn dở dang.
// Đủ cho vài nghìn đơn. Nhiều hơn thì đổi sang SQLite, chỉ phải thay file này.

import { readFile, writeFile, rename, mkdir } from 'node:fs/promises';
import { dirname } from 'node:path';

export class Store {
  constructor(path){ this.path = path; this.data = { seq: 0, orders: {} }; this.lock = Promise.resolve(); }

  async init(){
    try { this.data = JSON.parse(await readFile(this.path, 'utf8')); }
    catch { await mkdir(dirname(this.path), { recursive: true }); await this.flush(); }
    return this;
  }

  async flush(){
    const tmp = this.path + '.tmp';
    await writeFile(tmp, JSON.stringify(this.data, null, 2));
    await rename(tmp, this.path);
  }

  // mọi thay đổi xếp hàng một lượt, không có hai lần ghi đè nhau
  write(fn){
    this.lock = this.lock.then(async () => {
      const out = await fn(this.data);
      await this.flush();
      return out;
    });
    return this.lock;
  }

  nextCode(){
    this.data.seq++;
    const d = new Date();
    const ym = String(d.getFullYear()).slice(2) + String(d.getMonth()+1).padStart(2,'0');
    return 'DVR' + ym + String(this.data.seq).padStart(4,'0');
  }

  get(code){ return this.data.orders[String(code || '').toUpperCase()] || null; }
  all(){ return Object.values(this.data.orders).sort((a,b) => b.createdAt.localeCompare(a.createdAt)); }
}
