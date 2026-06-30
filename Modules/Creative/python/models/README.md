# Super-resolution modelleri

`enhance.py`, OpenCV `dnn_superres` ile görsel büyütmek için bu klasördeki `.pb`
model dosyalarını kullanır. Model dosyaları **repo'ya dahil değildir** (boyut); kurulumda
buraya indirilmelidir. Model yoksa `enhance.py` otomatik olarak Pillow LANCZOS
fallback'ine düşer (çalışmaya devam eder, sadece "gerçek" detay sentezi olmaz).

Dosya adı, config'teki `model_name` ve `scale` ile eşleşmelidir:
- `CREATIVE_ENHANCE_MODEL=fsrcnn`, `CREATIVE_ENHANCE_SCALE=2` → `FSRCNN_x2.pb`

## Önerilen: FSRCNN (küçük, hızlı, CPU dostu)

Kaynak: https://github.com/Saafke/FSRCNN_Tensorflow/tree/master/models

```bash
# models/ klasöründen:
curl -L -o FSRCNN_x2.pb https://raw.githubusercontent.com/Saafke/FSRCNN_Tensorflow/master/models/FSRCNN_x2.pb
curl -L -o FSRCNN_x4.pb https://raw.githubusercontent.com/Saafke/FSRCNN_Tensorflow/master/models/FSRCNN_x4.pb
```

## Opsiyonel: EDSR (daha kaliteli, daha ağır ~38MB/x4)

Kaynak: https://github.com/Saafke/EDSR_Tensorflow/tree/master/models
`CREATIVE_ENHANCE_MODEL=edsr` ile kullanılır (ör. `EDSR_x4.pb`).

## Desteklenen model adları (OpenCV)

`fsrcnn`, `edsr`, `lapsrn`, `espcn`. Her birinin `_x2/_x3/_x4` varyantları vardır
(LapSRN `_x8` da destekler). `model_name` ve dosya adındaki ölçek, config `scale` ile
tutarlı olmalıdır.
