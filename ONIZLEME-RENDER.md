# Tekayper önizleme sürümü: Render'da yayına alma

Amaç: müşterilerin siteyi gözden geçirebilmesi için **geçici** bir adres. Sitede "Önizleme sürümü · yayında değil" bandı görünür, arama motorlarına kapalıdır (noindex, robots.txt), form e-posta göndermez (talepler sunucu günlüğüne yazılır). Şifre yoktur; adresi bilen herkes açar. Canlı site (tekayper.com) bundan etkilenmez.

## 1. GitHub'a yükleme (herkese açık depo)

1. github.com'da **Public** bir depo oluşturun (ör. `tekayper-onizleme`). README/lisans eklemeyin.
2. Hesabınızdan bir erişim anahtarı (token) alın: Settings → Developer settings → Personal access tokens → Fine-grained → yalnızca bu depo, "Contents: Read and write".
3. Proje klasöründe (`tekayper-yeni`) komutlar. `KULLANICI`, `DEPO` ve `TOKEN` yerine kendi değerlerinizi yazın:

```
git remote add origin https://KULLANICI:TOKEN@github.com/KULLANICI/DEPO.git
git push -u origin main
git remote set-url origin https://github.com/KULLANICI/DEPO.git
```

Son komut token'ı yerel ayardan siler. Depo herkese açık olduğundan içine yalnızca site dosyaları girer (`_kaynak`, `_qa`, `_spec`, `_arastirma` ve gizli ayarlar `.gitignore` ile dışarıda).

## 2. Render'da kurulum

1. render.com'da hesap açın, GitHub'ı bağlayın.
2. **New → Blueprint** → deponuzu seçin. `render.yaml` okunur: ücretsiz plan, Docker, Frankfurt. (Olmazsa: **New → Web Service** → depo → Runtime: Docker, Instance: Free, Region: Frankfurt, Health Check Path: `/saglik.html`, ortam değişkeni `TEKAYPER_ONIZLEME=1`.)
3. **Apply / Deploy** deyin. İlk derleme birkaç dakika sürer. Bitince `https://tekayper-onizleme.onrender.com` benzeri bir adres verilir.

## 3. Bağlantıyı paylaşma

Adresi müşterilere gönderin. Şifre yok. Gerekirse sonradan şifre koymak için Render → Environment bölümüne `ONIZLEME_SIFRE` ekleyin (isteğe bağlı `ONIZLEME_KULLANICI`, boşsa `onizleme`); site yeniden başlayınca kimlik sorar.

## 4. Bilmeniz gerekenler

- **Soğuk başlangıç:** ücretsiz planda site 15 dakika istek almazsa uyur; ilk açılış 30-60 saniye sürebilir. Müşteriye "ilk açılış biraz yavaş" diye belirtin.
- Form denemeleri e-posta göndermez; kayıtlar sunucuda tutulur ve yeniden başlayınca silinir.
- Sitede değişiklik yaptıktan sonra `git push` yeterlidir; otomatik yayın kapalıdır, Render'da **Manual Deploy** ile yenileyin.

## 5. Kaldırma

Render panelinde servis → Settings → en altta **Delete Web Service**. İsterseniz GitHub deposunu da Settings → Danger Zone → Delete this repository ile silin. Bitince adres çalışmaz.
