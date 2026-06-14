<?php
/**
 * Mitgliedschaft-Seite: Block-Pattern + automatische Seitenerstellung.
 *
 * Stellt die komplette Sektionsstruktur als Gutenberg-Pattern bereit
 * und legt beim ersten Aufruf einmalig die Seite „Mitgliedschaft" an.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Liefert den Gutenberg-Block-Inhalt der Mitgliedschafts-Seite.
 * SVG-Icons werden inline ausgegeben (decorativ, keine Übersetzung nötig).
 */
function tcg_mitgliedschaft_block_content() {
    // Inline-SVGs für bessere Lesbarkeit als Variablen
    $svg_arrow = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M3 7h8M7.5 3.5L11 7l-3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    $svg_family = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="1.6"/><circle cx="16" cy="8" r="3" stroke="currentColor" stroke-width="1.6"/><path d="M3 20c.5-3.5 3-5.5 5-5.5s4.5 2 5 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M13 20c.5-3.5 3-5.5 5-5.5s4.5 2 5 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';

    $svg_single = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.6"/><path d="M4 20c1-4 4-6 8-6s7 2 8 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';

    $svg_book = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2 7l8-3 8 3-8 3-8-3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M5 8.5V13c0 1.5 2.2 2.5 5 2.5s5-1 5-2.5V8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 7v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

    $svg_youth = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="7" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M3 18c1-3.5 3.5-5.5 7-5.5s6 2 7 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

    $svg_ball = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M5 10c3-2.5 7-2.5 10 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 5c-2.5 3-2.5 7 0 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

    $svg_clock = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v4l2.5 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

    $svg_info_clock = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M2 8a6 6 0 1112 0A6 6 0 012 8z" stroke="#1a6b35" stroke-width="1.5"/><path d="M8 5v3.5l2 1.5" stroke="#1a6b35" stroke-width="1.5" stroke-linecap="round"/></svg>';

    $svg_calendar = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="2" y="3" width="12" height="11" rx="1.5" stroke="#1a6b35" stroke-width="1.5"/><path d="M5 2v2M11 2v2M2 7h12" stroke="#1a6b35" stroke-width="1.5" stroke-linecap="round"/></svg>';

    $svg_list = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M2 8l2 2 4-4M10 12h4M10 9h4M10 6h4" stroke="#1a6b35" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    $svg_doc = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 5h8M4 8h8M4 11h5" stroke="#1a6b35" stroke-width="1.5" stroke-linecap="round"/><rect x="2" y="2" width="12" height="12" rx="1.5" stroke="#1a6b35" stroke-width="1.5"/></svg>';

    $svg_pdf = '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M5 2h6l3 3v11a1 1 0 01-1 1H5a1 1 0 01-1-1V3a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M10 2v4h4" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>';

    $svg_pdf2 = '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M4 3h10v12H4z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M6 6h6M6 9h6M6 12h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

    $svg_mail = '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M2 6.5A1.5 1.5 0 013.5 5h11A1.5 1.5 0 0116 6.5v6a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 012 12.5v-6z" stroke="currentColor" stroke-width="1.5"/><path d="M2 7l7 4 7-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

    // Tier-Karten als String-Templates
    $tier_family_card = <<<HTML
<div class="mb-tier-card">
  <div class="mb-tier-head">
    <div>
      <h3 class="mb-tier-name">Familienbeitrag</h3>
      <p class="mb-tier-tagline">Ehepaare &amp; Lebensgemeinschaften mit Kindern bis 13 Jahre*</p>
    </div>
    <div class="mb-tier-icon">{$svg_family}</div>
  </div>
  <div class="mb-tier-price">
    <span class="mb-tier-price-currency">€</span>
    <span class="mb-tier-price-num">280</span>
    <span class="mb-tier-price-unit">/ Jahr</span>
  </div>
  <ul class="mb-tier-perks">
    <li>Beide Erwachsene unbegrenzt spielberechtigt</li>
    <li>Kinder bis 13 Jahre kostenfrei im Beitrag enthalten</li>
    <li>Freie Platzwahl auf allen 6 Sandplätzen</li>
    <li>Teilnahme am Mannschaftsspielbetrieb</li>
    <li>Vergünstigte Trainerstunden für Mitglieder</li>
    <li>Vereinsleben &amp; alle Mitgliederveranstaltungen</li>
  </ul>
  <a href="#" class="mb-tier-cta">Familie anmelden</a>
</div>
HTML;

    $tier_single_card = <<<HTML
<div class="mb-tier-card">
  <div class="mb-tier-head">
    <div>
      <h3 class="mb-tier-name">Einzelbeitrag</h3>
      <p class="mb-tier-tagline">Einzelpersonen ab 18 Jahren &amp; Alleinerziehende mit Kindern bis 13 Jahre*</p>
    </div>
    <div class="mb-tier-icon">{$svg_single}</div>
  </div>
  <div class="mb-tier-price">
    <span class="mb-tier-price-currency">€</span>
    <span class="mb-tier-price-num">170</span>
    <span class="mb-tier-price-unit">/ Jahr</span>
  </div>
  <ul class="mb-tier-perks">
    <li>Unbegrenztes Spielrecht auf allen Plätzen</li>
    <li>Bei Alleinerziehenden: Kinder bis 13 J. inklusive</li>
    <li>Teilnahme am Mannschaftsspielbetrieb</li>
    <li>Vergünstigte Trainerstunden für Mitglieder</li>
    <li>Vereinsleben &amp; alle Mitgliederveranstaltungen</li>
    <li>Mannschaftsmeldung auch ohne Familie möglich</li>
  </ul>
  <a href="#" class="mb-tier-cta">Jetzt anmelden</a>
</div>
HTML;

    $exceptions_block = <<<HTML
<div class="mb-exceptions">
  <div class="mb-exceptions-head">
    <div>
      <h3>Ausnahmen &amp; ermäßigte Beiträge</h3>
      <p>Für junge Mitglieder, Auszubildende und alle, die gerade pausieren — wir möchten Tennis für jeden zugänglich machen.</p>
    </div>
    <span class="mb-exceptions-note">Alle Preise pro Jahr</span>
  </div>
  <div class="mb-exc-grid">
    <div class="mb-exc-card">
      <div class="mb-exc-icon">{$svg_book}</div>
      <div>
        <div class="mb-exc-title">Studenten &amp; Azubis</div>
        <div class="mb-exc-desc">Studenten, Zivildienstleistende, Wehrpflichtige &amp; Auszubildende. Nachweis erforderlich.</div>
      </div>
      <div class="mb-exc-price">
        <span class="mb-exc-price-num">€&nbsp;100</span>
        <span class="mb-exc-price-unit">/ Jahr</span>
      </div>
    </div>
    <div class="mb-exc-card">
      <div class="mb-exc-icon">{$svg_youth}</div>
      <div>
        <div class="mb-exc-title">Jugendliche bis 17 J.</div>
        <div class="mb-exc-desc">Kinder &amp; Jugendliche, die nicht im Familienbeitrag enthalten sind.</div>
      </div>
      <div class="mb-exc-price">
        <span class="mb-exc-price-num">€&nbsp;80</span>
        <span class="mb-exc-price-unit">/ Jahr</span>
      </div>
    </div>
    <div class="mb-exc-card">
      <div class="mb-exc-icon">{$svg_ball}</div>
      <div>
        <div class="mb-exc-title">Kinder bis 13 J.</div>
        <div class="mb-exc-desc">Einzelmeldung für Kinder bis 13 Jahre außerhalb eines Familienbeitrags.</div>
      </div>
      <div class="mb-exc-price">
        <span class="mb-exc-price-num">€&nbsp;60</span>
        <span class="mb-exc-price-unit">/ Jahr</span>
      </div>
    </div>
    <div class="mb-exc-card">
      <div class="mb-exc-icon">{$svg_clock}</div>
      <div>
        <div class="mb-exc-title">Ruhende Mitgliedschaft</div>
        <div class="mb-exc-desc">Für Mitglieder, die aktuell pausieren — wir halten dir den Platz frei.</div>
      </div>
      <div class="mb-exc-price">
        <span class="mb-exc-price-num">€&nbsp;60</span>
        <span class="mb-exc-price-unit">/ Jahr</span>
      </div>
    </div>
  </div>
</div>
HTML;

    $info_row_block = <<<HTML
<div class="mb-info-row">
  <div class="mb-info-card">
    <h3>Gut zu wissen</h3>
    <div class="mb-info-list">
      <div class="mb-info-list-item">
        <div class="mb-info-list-item-icon">{$svg_info_clock}</div>
        <div class="mb-info-list-item-text">
          <strong>Keine Aufnahmegebühr</strong>
          <p>Du zahlst nur den Jahresbeitrag — keine versteckten Einmalkosten oder Aufnahmegebühren beim Eintritt.</p>
        </div>
      </div>
      <div class="mb-info-list-item">
        <div class="mb-info-list-item-icon">{$svg_calendar}</div>
        <div class="mb-info-list-item-text">
          <strong>Beitragsjahr = Kalenderjahr</strong>
          <p>Der Jahresbeitrag wird zu Beginn des Kalenderjahres per SEPA-Lastschrift eingezogen. Bei Eintritt nach dem 1. Juli wird der halbe Beitrag berechnet.</p>
        </div>
      </div>
      <div class="mb-info-list-item">
        <div class="mb-info-list-item-icon">{$svg_list}</div>
        <div class="mb-info-list-item-text">
          <strong>* Familienbeitrag — Kinder bis 13 Jahre</strong>
          <p>Kinder bis einschließlich 13 Jahre sind im Familien- bzw. Alleinerziehenden-Beitrag enthalten. Ab 14 Jahren gilt der Jugendbeitrag von 80 €.</p>
        </div>
      </div>
      <div class="mb-info-list-item">
        <div class="mb-info-list-item-icon">{$svg_doc}</div>
        <div class="mb-info-list-item-text">
          <strong>Kündigung</strong>
          <p>Eine Kündigung ist schriftlich bis zum 31. Oktober für das folgende Vereinsjahr möglich.</p>
        </div>
      </div>
    </div>
  </div>
  <div class="mb-info-card mb-docs-card">
    <div>
      <h3>Formulare &amp; Dokumente</h3>
      <p>Mitgliedsantrag und Satzung als PDF zum Download — einfach ausfüllen und im Vereinsheim abgeben oder per Mail senden.</p>
    </div>
    <div class="mb-docs-links">
      <a href="#" class="mb-docs-link">
        <div class="mb-docs-link-icon">{$svg_pdf}</div>
        <div class="mb-docs-link-body">
          <div class="mb-docs-link-title">Mitgliedsantrag</div>
          <div class="mb-docs-link-meta">PDF · ca. 120 KB</div>
        </div>
        <span class="mb-docs-link-arrow" aria-hidden="true"></span>
      </a>
      <a href="#" class="mb-docs-link">
        <div class="mb-docs-link-icon">{$svg_pdf2}</div>
        <div class="mb-docs-link-body">
          <div class="mb-docs-link-title">Vereinssatzung</div>
          <div class="mb-docs-link-meta">PDF · Stand 2021</div>
        </div>
        <span class="mb-docs-link-arrow" aria-hidden="true"></span>
      </a>
      <a href="mailto:info@tcg-passau.de" class="mb-docs-link">
        <div class="mb-docs-link-icon">{$svg_mail}</div>
        <div class="mb-docs-link-body">
          <div class="mb-docs-link-title">Fragen? Direkt schreiben</div>
          <div class="mb-docs-link-meta">info@tcg-passau.de</div>
        </div>
        <span class="mb-docs-link-arrow" aria-hidden="true"></span>
      </a>
    </div>
  </div>
</div>
HTML;

    $cta_block = <<<HTML
<div class="mb-cta-card">
  <div>
    <h3>Bereit für deinen ersten Aufschlag?</h3>
    <p>Komm zum kostenfreien Schnuppertraining vorbei, lerne die Plätze und Mitglieder kennen — und entscheide dich danach in Ruhe.</p>
  </div>
  <div class="mb-cta-actions">
    <a href="#" class="mb-cta-btn mb-cta-btn-primary">Jetzt Mitglied werden</a>
    <a href="#" class="mb-cta-btn mb-cta-btn-secondary">Schnuppertraining buchen</a>
  </div>
</div>
HTML;

    // Gesamter Gutenberg-Inhalt
    return <<<BLOCKS
<!-- wp:html -->
<div class="mb-label"><span class="mb-label-text">Jahresbeiträge 2026</span><span class="mb-label-line"></span></div>
<!-- /wp:html -->

<!-- wp:group {"className":"mb-intro"} -->
<div class="wp-block-group mb-intro">
<!-- wp:heading -->
<h2 class="wp-block-heading">Zwei Beitragsmodelle für jede Lebenssituation.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ob als Familie oder als Einzelspieler — unsere Hauptbeiträge decken den vollen Spielbetrieb über die gesamte Sommersaison ab. Inklusive Platzbenutzung, Vereinsleben und Mitgliederrabatten.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:html -->
<div class="mb-tier-grid">
{$tier_family_card}
{$tier_single_card}
</div>
<!-- /wp:html -->

<!-- wp:html -->
<div class="mb-label"><span class="mb-label-text">Ermäßigte Tarife</span><span class="mb-label-line"></span></div>
<!-- /wp:html -->

<!-- wp:html -->
{$exceptions_block}
<!-- /wp:html -->

<!-- wp:html -->
{$info_row_block}
<!-- /wp:html -->

<!-- wp:html -->
{$cta_block}
<!-- /wp:html -->
BLOCKS;
}

/**
 * Registriert das Block-Pattern „Mitgliedschaft" für den Inserter.
 */
function tcg_register_mitgliedschaft_pattern() {
    if ( ! function_exists( 'register_block_pattern' ) ) {
        return;
    }

    register_block_pattern( 'tcg/mitgliedschaft-page', [
        'title'       => __( 'Mitgliedschaft – komplette Seite', 'tc-grubweg' ),
        'description' => __( 'Beitrags-/Mitgliedschaftsseite mit Tier-Karten, Ausnahmen, Hinweisen, Dokumenten und CTA.', 'tc-grubweg' ),
        'categories'  => [ 'tcg' ],
        'content'     => tcg_mitgliedschaft_block_content(),
    ] );
}
add_action( 'init', 'tcg_register_mitgliedschaft_pattern' );

/**
 * Registriert eine eigene Pattern-Kategorie „TC Grubweg".
 */
function tcg_register_pattern_category() {
    if ( ! function_exists( 'register_block_pattern_category' ) ) {
        return;
    }
    register_block_pattern_category( 'tcg', [
        'label' => __( 'TC Grubweg', 'tc-grubweg' ),
    ] );
}
add_action( 'init', 'tcg_register_pattern_category', 9 );

/**
 * Legt einmalig die Seite „Mitgliedschaft" an, falls sie noch nicht existiert.
 * Verwendet eine Option als Marker, damit die Seite nach Löschung nicht wiederkehrt.
 */
function tcg_create_mitgliedschaft_page() {
    if ( get_option( 'tcg_mitgliedschaft_page_created' ) ) {
        return;
    }

    // Auch nicht erstellen, wenn bereits eine Seite mit Slug „mitgliedschaft" existiert.
    $existing = get_page_by_path( 'mitgliedschaft' );
    if ( $existing ) {
        update_option( 'tcg_mitgliedschaft_page_created', $existing->ID );
        return;
    }

    // kses-Filter temporär entfernen, damit Inline-SVGs nicht herausgefiltert werden.
    kses_remove_filters();

    $page_id = wp_insert_post( [
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'post_title'     => 'Mitgliedschaft & Beiträge',
        'post_name'      => 'mitgliedschaft',
        'post_excerpt'   => 'Faire Beiträge, keine Aufnahmegebühr, unbegrenztes Tennis von April bis Oktober. Beim DJK-TC Passau Grubweg spielen Familien, Einzelpersonen und Jugendliche zu transparenten Konditionen.',
        'post_content'   => tcg_mitgliedschaft_block_content(),
        'page_template'  => 'page-mitgliedschaft.php',
        'comment_status' => 'closed',
        'ping_status'    => 'closed',
    ] );

    kses_init_filters();

    if ( $page_id && ! is_wp_error( $page_id ) ) {
        update_option( 'tcg_mitgliedschaft_page_created', $page_id );
    }
}
add_action( 'init', 'tcg_create_mitgliedschaft_page', 20 );
