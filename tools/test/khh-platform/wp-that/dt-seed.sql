CREATE TABLE IF NOT EXISTS wp_khmatrix.wp_khh_dt_ngay (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT, ngay date NOT NULL, cua_hang varchar(190) NOT NULL DEFAULT '',
  pos_id varchar(40) NOT NULL DEFAULT '', doanh_thu double NOT NULL DEFAULT 0, chiet_khau double NOT NULL DEFAULT 0,
  thanh_tien double NOT NULL DEFAULT 0, so_hd int(11) NOT NULL DEFAULT 0, so_mon double NOT NULL DEFAULT 0, so_ve double NOT NULL DEFAULT 0,
  gio longtext NOT NULL, pttt longtext NOT NULL, nguon longtext NOT NULL, mon longtext NOT NULL, nap_luc bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY ngay_ch (ngay,cua_hang(120)), KEY ngay (ngay)) CHARACTER SET utf8mb4;
DELETE FROM wp_khmatrix.wp_khh_dt_ngay;
INSERT INTO wp_khmatrix.wp_khh_dt_ngay (ngay,cua_hang,pos_id,doanh_thu,thanh_tien,so_hd,gio,pttt,nguon,mon) VALUES
 ('2026-09-15','KHU VUI CHƠI FUNFEST','POS1',12500000,12000000,86,'','','',''),
 ('2026-09-15','FZ ADV AL','POS2',8400000,8100000,51,'','','',''),
 ('2026-09-16','KHU VUI CHƠI FUNFEST','POS1',9800000,9500000,70,'','','',''),
 ('2020-01-01','KHU VUI CHƠI FUNFEST','POS1',1,1,1,'','','','');
