1. **Установка зависимостей**
   ```bash
   cd docker/php 
   docker compose up -d --build
   docker exec -it ii_server-php-1 /bin/sh
   Внутри контейнера выполнить:
   composer install
   php bin/console doctrine:migrations:migrate
   php bin/console doctrine:fixtures:load --append

<hr>
<center>
<h1>АРХИТЕКТУРНЫЙ КОНЦЕПТ</h1>
<a href="https://www.edraw.ai/app/board/0a1FZ8I5SBY3Eq6wWUsADL9hFcUD4yhk?ivt=82a6f718f13f29d8c1446d2f276d9818eae897c1ca005afda98525abe358eea5d6a2608570c6c119392ac4a415809508">
>>>Смотреть карту<<<
</a>
</center>
<hr>
<center>
<H1><b>Технические требовании</b></h1>
<h2>Ресайз изображении</h2>
</center>
по <u>HEIGHT</u> не ограничено
<hr>
по <b>WIDTH</b>:

1. <u>Для значков</u><br>
только <b>WEBM</b> |
качество 5-6 из 6 <br>
> == 300px (не меньше и не больше)
2. <u>Средние изображение</u><br>
только WEBM | качество 5-6 из 6 <br>
><= 1920px (Если изображение большое то ресайзим)
3. <u>Для детального просмотра</u> <br>
Только JPEG | качество 60+ из 100<br>
><= 5760px (Если изображение большое то ресайзим)
