Jesteś Senior PHP developerem Laravel.

Chcialbym abys zrobic w kolejce scrapowanie przepisów.


## Scrapowanie kategorii
Niech bedzie komenda gdzie podam url do kategorii. "php artisan scrap:category url=https://aniagotuje.pl/przepisy/dania-miesne"
Do osobnej tabeli bedzie zapisywany url do danego przepisu z informacja czy przepis zostal juz zescrapowany domyślnie false.
Tabela ScrapCategories
string: url
bool: is_scraped
string: type (domyslnie: ania-gotuje)

Wynik komendy powinien byc ile przepisów zescrapowano w bazie danych
Pamietajmy ze w tym kroku my tylko zaciagamy url do przepisow a nie scrapujemy wszystkie informacje o przepisach


### Paginacja
Ważne aby obslugiwal paginacje 

Dla przykladu strona glowna podkategorii to: https://aniagotuje.pl/przepisy/dania-miesne
a kolejne podstrony to:
https://aniagotuje.pl/przepisy/dania-miesne/strona/2
https://aniagotuje.pl/przepisy/dania-miesne/strona/3

Przykładowa strona zescrapowana
storage/app/private/pages/aniagotuje-categories.html

### Walka z zablokowaniem
Niech przed wejsciem na podstrone odczceka ze 1-3 sekundy (zeby nie zablokowalo moje ip)




## Scrapowanie przepisu

Przykład zescrapowane przepisu: 
- storage/app/private/pages/aniagotuje-rolada.html
- storage/app/private/pages/aniagotuje-miso-satay-gyoza.html


### Tabela: ScrapRecipe
Utwórz nowy model gdzie beda sie bezposrednio zapisywac informacje danego przepisu jak:
nazwa, url, kategoria
Czas przygotowania,
Liczba porcji,
Macro, składniki (uwaga moga byc podzielone np ciasto, dodatki itd),
kroki, zdjecia



Utworz serwis ktory jako parametr dostanie url do przepisyu np: "https://aniagotuje.pl/przepis/miso-satay-gyoza" i ten serwis bedzie scrapowal dane i umieszczal dane w bazie.


(pozniej zrobimy kolejkowanie ale nie teraz. Teraz chce sie upewnic i zrobic dobrze proces scrapowania, chce miec pewnosc ze wszystko dziala zanim przeskanuje cala strone)







Teraz zrobmy scraper dla strony smaker.pl podobny do \App\Services\AniaGotujeScraper

Wazne chcialbym aby \App\Console\Commands\ScrapCategoryCommand na podstawie url uruchamialo odpowiedni proces czyli ze ma pobrac przepisy w tyn przypadku smaker

Tak samo zrob dedykowany scraper dla strony smaker.

Kilka słów:


### Przyklad kategorii:
storage/app/private/pages/przepisy-smaker-dania-glowne.html

### Przyklad przepisu:

storage/app/private/pages/przepis-smaker-tteokbokki.html

### Paginacja:

https://smaker.pl/przepisy-dania-glowne
https://smaker.pl/przepisy-dania-glowne?page=2
https://smaker.pl/przepisy-dania-glowne?page=5

Zaktualizuj Services/ScraperFactory.php
