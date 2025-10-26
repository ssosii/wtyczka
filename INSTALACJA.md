# Instrukcja instalacji wtyczki Rezerwacje

## Wymagania

- WordPress 5.0 lub nowszy
- PHP 7.4 lub nowszy
- MySQL 5.6 lub nowszy

## Instalacja

### Krok 1: Przygotowanie plików

1. Skopiuj cały folder `rezerwacje` do katalogu `/wp-content/plugins/` na Twoim serwerze WordPress
2. Upewnij się, że struktura katalogów wygląda następująco:

```
wp-content/
  plugins/
    rezerwacje/
      admin/
        css/
        js/
        class-admin.php
        class-admin-bookings.php
        class-admin-services.php
        class-admin-therapists.php
      includes/
        class-availability.php
        class-booking.php
        class-database.php
        class-email.php
        class-service.php
        class-therapist.php
      public/
        css/
        js/
        class-frontend.php
      rezerwacje.php
```

### Krok 2: Aktywacja wtyczki

1. Zaloguj się do panelu administracyjnego WordPress
2. Przejdź do **Wtyczki** → **Zainstalowane wtyczki**
3. Znajdź wtyczkę **Rezerwacje** i kliknij **Aktywuj**
4. Wtyczka automatycznie utworzy wymagane tabele w bazie danych

### Krok 3: Konfiguracja początkowa

#### A. Dodanie pierwszej usługi

1. W menu WordPress przejdź do **Rezerwacje** → **Usługi**
2. Kliknij **Dodaj nową**
3. Wypełnij formularz:
   - **Nazwa**: np. "Masaż relaksacyjny"
   - **Opis**: opcjonalny opis usługi
   - **Czas trwania**: np. 60 minut
   - **Cena domyślna**: np. 100 zł
   - **Status**: zaznacz "Aktywna"
4. Kliknij **Zapisz**

#### B. Dodanie terapeuty

1. Najpierw utwórz konto użytkownika WordPress dla terapeuty:
   - Przejdź do **Użytkownicy** → **Dodaj nowego**
   - Nadaj rolę: **Redaktor** lub **Autor**
   - Zapisz użytkownika

2. Następnie dodaj terapeutę:
   - Przejdź do **Rezerwacje** → **Terapeuci**
   - Kliknij **Dodaj nowego**
   - Wypełnij formularz:
     - **Użytkownik WordPress**: wybierz utworzonego użytkownika
     - **Imię i nazwisko**: np. "Jan Kowalski"
     - **Email**: adres email terapeuty
     - **Telefon**: opcjonalny numer telefonu
     - **Bio**: opcjonalny opis terapeuty
     - **Status**: zaznacz "Aktywny"
   - Kliknij **Zapisz**

#### C. Przypisanie usług do terapeuty

1. Przejdź do **Rezerwacje** → **Terapeuci**
2. Znajdź terapeutę i kliknij **Usługi**
3. Zaznacz checkboxy przy usługach, które terapeuta oferuje
4. Opcjonalnie: ustaw **Cenę własną** jeśli terapeuta ma inną cenę niż domyślna
5. Kliknij **Zapisz**

#### D. Ustawienie dostępności terapeuty

1. Przejdź do **Rezerwacje** → **Terapeuci**
2. Znajdź terapeutę i kliknij **Dostępność**
3. Dodaj przedziały czasowe, w których terapeuta jest dostępny:
   - **Dzień tygodnia**: np. Poniedziałek
   - **Godzina od**: np. 10:00
   - **Godzina do**: np. 12:00
4. Kliknij **Dodaj**
5. Powtórz dla wszystkich przedziałów czasowych

### Krok 4: Utworzenie strony rezerwacji

1. Przejdź do **Strony** → **Dodaj nową**
2. Nadaj tytuł stronie, np. "Rezerwacje"
3. W treści strony wklej shortcode:
   ```
   [rezerwacje_kalendarz]
   ```
4. Opublikuj stronę
5. Skopiuj adres URL opublikowanej strony

Teraz użytkownicy mogą korzystać z systemu rezerwacji!

## Panel terapeuty

Terapeuta po zalogowaniu się na swoje konto WordPress będzie miał dostęp do:

1. **Moje Rezerwacje** - lista wszystkich rezerwacji
2. **Dostępność** - możliwość edycji swoich godzin pracy
3. **Usługi** - możliwość edycji cen swoich usług

### Ustawianie dostępności przez terapeutę

1. Terapeuta loguje się do WordPress
2. W menu bocznym widzi **Moje Rezerwacje**
3. Klikając w **Terapeuci** może edytować swoją dostępność
4. Może dodawać/usuwać przedziały czasowe

### Edycja cen przez terapeutę

1. Terapeuta przechodzi do **Rezerwacje** → **Terapeuci**
2. Znajduje siebie na liście i klika **Usługi**
3. W kolumnie **Cena własna** może wpisać swoją cenę
4. Jeśli pole zostanie puste, używana jest cena domyślna

## Panel administratora

Administrator ma pełny dostęp do wszystkich funkcji:

### Zarządzanie rezerwacjami

1. Przejdź do **Rezerwacje** → **Rezerwacje**
2. Zobacz listę wszystkich rezerwacji
3. Filtruj po statusie: Wszystkie / Oczekujące / Zatwierdzone / Odrzucone
4. Kliknij **Zatwierdź** lub **Odrzuć** dla rezerwacji oczekujących

### Blokowanie terminów

#### Pojedyncza blokada (np. wtorek 13:00 - Jan Kowalski + Michał Nowak)

1. Przejdź do **Rezerwacje** → **Rezerwacje**
2. Kliknij **Zablokuj termin**
3. Wypełnij formularz:
   - **Terapeuta**: Jan Kowalski
   - **Nazwa spotkania**: Michał Nowak
   - **Typ blokady**: Pojedyncza
   - **Data**: wybierz wtorek
   - **Godzina od**: 13:00
   - **Godzina do**: 14:00
4. Kliknij **Zablokuj termin**

#### Powtarzająca się blokada (np. co środę 18:00-18:30 przez 3 miesiące)

1. Przejdź do **Rezerwacje** → **Rezerwacje**
2. Kliknij **Zablokuj termin**
3. Wypełnij formularz:
   - **Terapeuta**: Jan Kowalski
   - **Nazwa spotkania**: Michał Nowak
   - **Typ blokady**: Powtarzająca się
   - **Dzień tygodnia**: Środa
   - **Data rozpoczęcia**: dzisiejsza data
   - **Data zakończenia**: data za 3 miesiące
   - **Godzina od**: 18:00
   - **Godzina do**: 18:30
4. Kliknij **Zablokuj termin**

### Przeglądanie zablokowanych terminów

1. Przejdź do **Rezerwacje** → **Rezerwacje**
2. Kliknij **Zablokowane terminy**
3. Zobacz listę wszystkich zablokowanych terminów
4. Kliknij **Usuń** aby usunąć blokadę

## Proces rezerwacji od strony pacjenta

1. Pacjent wchodzi na stronę rezerwacji
2. **Krok 1**: Wybiera terapeutę z listy
3. **Krok 2**: Wybiera usługę
4. **Krok 3**: Wybiera datę z kalendarza
5. **Krok 4**: Wybiera godzinę z dostępnych slotów (zajęte są ukryte)
6. **Krok 5**: Wypełnia swoje dane (imię, email, telefon, notatki)
7. Klika **Zarezerwuj**
8. Otrzymuje email z informacją, że rezerwacja oczekuje na potwierdzenie

## Powiadomienia email

System automatycznie wysyła następujące emaile:

### Do pacjenta:
- **Po utworzeniu rezerwacji**: potwierdzenie z informacją o oczekiwaniu na akceptację
- **Po zatwierdzeniu**: potwierdzenie rezerwacji
- **Po odrzuceniu**: informacja o odrzuceniu z prośbą o wybranie innego terminu

### Do terapeuty:
- **Po utworzeniu rezerwacji**: powiadomienie o nowej rezerwacji do akceptacji z linkiem do panelu

## Rozwiązywanie problemów

### Wtyczka nie pojawia się w menu

- Sprawdź uprawnienia użytkownika
- Upewnij się, że wtyczka jest aktywowana
- Sprawdź logi błędów PHP

### Nie przychodzą emaile

- Sprawdź ustawienia SMTP w WordPress
- Rozważ użycie wtyczki do wysyłki emaili jak WP Mail SMTP
- Sprawdź folder SPAM

### Kalendarz nie wyświetla się poprawnie

- Wyczyść cache przeglądarki
- Sprawdź czy pliki CSS i JS się ładują (konsola przeglądarki)
- Sprawdź czy nie ma konfliktów z motywem lub innymi wtyczkami

## Wsparcie

W razie problemów sprawdź:
1. Logi błędów WordPress
2. Logi błędów PHP na serwerze
3. Konsolę przeglądarki (F12) dla błędów JavaScript

## Dezinstalacja

Jeśli chcesz usunąć wtyczkę:

1. Najpierw zrób backup bazy danych!
2. Dezaktywuj wtyczkę
3. Usuń wtyczkę przez panel WordPress
4. Jeśli chcesz usunąć także dane, wykonaj w phpMyAdmin:

```sql
DROP TABLE IF EXISTS wp_rezerwacje_blocked_slots;
DROP TABLE IF EXISTS wp_rezerwacje_bookings;
DROP TABLE IF EXISTS wp_rezerwacje_availability;
DROP TABLE IF EXISTS wp_rezerwacje_therapist_services;
DROP TABLE IF EXISTS wp_rezerwacje_services;
DROP TABLE IF EXISTS wp_rezerwacje_therapists;
```

(Zamień `wp_` na prefix Twojej bazy danych, jeśli jest inny)
