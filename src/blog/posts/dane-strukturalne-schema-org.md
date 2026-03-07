---
title: "Dane strukturalne Schema.org — przewodnik dla początkujących"
date: 2026-03-08
tags:
  - SEO
  - Dane strukturalne
description: "Praktyczny przewodnik po danych strukturalnych Schema.org. Dowiedz się, jak dodać JSON-LD do swojej strony i poprawić widoczność w wyszukiwarkach."
author: "Jakub T. Janiewicz"
---

Dane strukturalne (structured data) to sposób na opisanie treści strony internetowej w formacie zrozumiałym dla wyszukiwarek. Schema.org to najczęściej używany słownik do tworzenia danych strukturalnych, wspierany przez Google, Microsoft, Yahoo i Yandex.

## Czym są dane strukturalne?

Dane strukturalne to dodatkowe informacje umieszczone w kodzie HTML strony, które pomagają wyszukiwarkom zrozumieć kontekst treści. Dzięki nim wyszukiwarki mogą wyświetlać **rozszerzone wyniki** (rich results), takie jak:

- Gwiazdki ocen produktów
- Czas przygotowania przepisów
- Daty i lokalizacje wydarzeń
- Panele wiedzy o organizacjach

## Format JSON-LD

Google rekomenduje format **JSON-LD** (JavaScript Object Notation for Linked Data) jako preferowany sposób wdrożenia danych strukturalnych:

```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "WikiZEIT",
  "url": "https://jcubic.pl/wikizeit/",
  "logo": "https://jcubic.pl/wikizeit/img/logo.svg",
  "description": "Projekt edukacyjny o Wikipedii i etycznym SEO"
}
```

JSON-LD jest wygodny, ponieważ:

1. Umieszczany jest w tagu `<script>`, oddzielnie od treści HTML
2. Łatwo go generować programowo
3. Nie wymaga modyfikacji istniejącej struktury HTML

## Typy Schema.org przydatne w SEO

Najczęściej używane typy w kontekście SEO to:

| Typ | Zastosowanie |
|-----|-------------|
| `Organization` | Firmy i organizacje |
| `Person` | Osoby publiczne, autorzy |
| `Article` / `BlogPosting` | Artykuły i wpisy blogowe |
| `WebPage` | Strony internetowe |
| `BreadcrumbList` | Nawigacja breadcrumb |
| `FAQPage` | Strony z często zadawanymi pytaniami |

## Testowanie danych strukturalnych

Google udostępnia narzędzie do testowania danych strukturalnych: [Rich Results Test](https://search.google.com/test/rich-results). Warto sprawdzić swoje dane przed wdrożeniem na produkcję.

## Podsumowanie

Dane strukturalne to jeden z najważniejszych elementów technicznego SEO. Projekt WikiZEIT wykorzystuje JSON-LD Schema.org na każdej stronie, aby zapewnić maksymalną widoczność w wynikach wyszukiwania.
