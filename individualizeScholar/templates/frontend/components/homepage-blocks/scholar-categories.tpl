{**
 * Individualize Scholar homepage block: one card per category with articles
 * in the current issue (name and number of articles; the card opens the
 * list of its articles).
 *}
{if $individualizeScholarCategories}
  <section class="homepage-block homepage-block-scholar-categories">
    {$individualizeScholarCategories}
  </section>
{/if}
