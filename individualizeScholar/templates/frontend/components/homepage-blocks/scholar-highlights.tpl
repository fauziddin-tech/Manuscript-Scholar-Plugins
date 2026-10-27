{**
 * Individualize Scholar homepage block: latest, most read and most
 * downloaded articles (three cards). Shown only when at least one card has
 * articles.
 *}
{if $individualizeScholarHighlights}
  <section class="homepage-block homepage-block-scholar-highlights">
    {$individualizeScholarHighlights}
  </section>
{/if}
