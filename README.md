# WHMCS Paymes Sanal POS Modülü #

## Kurulum ##

* Öncelikle https://web.paym.es üyeliği gerektirir. Sonra http://bit.ly/PaymesApi
adresinden API erişimi için başvurmanız ve sonrasında Paymes'ten gelecek olan
emailde istenen bilgi ve belgeleri tamamlayıp göndermeniz gerekir. Sözleşme ve
diğer belgeler tam ise, Paymes size ait bir Secret Key verecek.

* Dosyaları WHMCS kurulumunuza yükleyin.

```
 modules/gateways/
  |- callback/paymes.php
  |  paymes.php
```

* https://<whmcs_site_adresi>/admin/configgateways.php adresinden 'Paymes Sanal POS' isimli
ödeme yöntemini aktifleştirin.

* Secret Key alanına Paymes'in size gönderdiği Secret Key bilgisini yapıştırıp
kaydedin.
