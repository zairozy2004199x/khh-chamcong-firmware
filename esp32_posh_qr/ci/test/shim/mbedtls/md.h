/* mbedtls GIẢ cho bài test trên máy tính — nối thẳng sang OpenSSL. */
#pragma once
#include <string>
#include <cstdint>
#include <openssl/hmac.h>

typedef int mbedtls_md_info_t;
#define MBEDTLS_MD_SHA256 1
struct mbedtls_md_context_t { std::string khoa, noiDung; };

inline const mbedtls_md_info_t* mbedtls_md_info_from_type(int) { static mbedtls_md_info_t i = 1; return &i; }
inline void mbedtls_md_init(mbedtls_md_context_t* c) { c->khoa.clear(); c->noiDung.clear(); }
inline int  mbedtls_md_setup(mbedtls_md_context_t*, const mbedtls_md_info_t*, int) { return 0; }
inline int  mbedtls_md_hmac_starts(mbedtls_md_context_t* c, const uint8_t* k, size_t n) {
  c->khoa.assign((const char*)k, n); c->noiDung.clear(); return 0;
}
inline int  mbedtls_md_hmac_update(mbedtls_md_context_t* c, const uint8_t* d, size_t n) {
  c->noiDung.append((const char*)d, n); return 0;
}
inline int  mbedtls_md_hmac_finish(mbedtls_md_context_t* c, uint8_t* out) {
  unsigned len = 0;
  HMAC(EVP_sha256(), c->khoa.data(), (int)c->khoa.size(),
       (const unsigned char*)c->noiDung.data(), c->noiDung.size(), out, &len);
  return 0;
}
inline void mbedtls_md_free(mbedtls_md_context_t* c) { c->khoa.clear(); c->noiDung.clear(); }
