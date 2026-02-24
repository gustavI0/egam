# Theme SDC & Template Refactor Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Eliminate ~350 lines of duplicated template markup by creating an `entity_teaser` SDC, fixing `illustration_container`, adding two Twig partials, and a macro file.

**Architecture:** New `entity_teaser` SDC replaces 5 near-identical teaser templates. A `partials/entity-detail-layout.html.twig` partial extracts the shared full-page skeleton. A `partials/related-content.html.twig` partial extracts the identical related-items section. A `macros/info-rows.html.twig` file provides macros for table rows.

**Tech Stack:** Drupal 11 SDC (Single Directory Components), Twig 3, DDEV local environment

---

## Context & Key Paths

Theme root: `web/themes/custom/egam/`
- SDC components: `web/themes/custom/egam/components/`
- Templates: `web/themes/custom/egam/templates/`
- Teaser templates: `templates/content/{entity}/{entity}--teaser.html.twig`
- Full-page templates: `templates/content/{entity}/{entity}.html.twig`

Entities: `artwork`, `artist`, `game`, `museum`, `screenshot`

**Clear cache command:** `ddev drush cr`
**View site:** `ddev launch`

### Twig path conventions
- SDC includes: `egam:component_name`
- Regular template includes/imports: `@egam/path/relative/to/templates-dir.html.twig`

---

## Task 1: Create `entity_teaser` SDC

**Files:**
- Create: `web/themes/custom/egam/components/entity_teaser/entity_teaser.component.yml`
- Create: `web/themes/custom/egam/components/entity_teaser/entity_teaser.twig`
- Create: `web/themes/custom/egam/components/entity_teaser/entity_teaser.css`

### Step 1: Create component definition

`web/themes/custom/egam/components/entity_teaser/entity_teaser.component.yml`:
```yaml
name: Entity Teaser
slots:
  cover:
    title: Cover
    description: Rendered cover image (with thumbnail fallback if applicable)
  label:
    title: Label
    description: Rendered or raw entity title, optionally wrapped in a link
  overlay:
    title: Overlay
    description: Optional hover overlay content (used by screenshot teaser for artwork list)
```

### Step 2: Create component template

`web/themes/custom/egam/components/entity_teaser/entity_teaser.twig`:
```twig
<div class="cover">
    {{ cover }}
    {% if overlay %}
        {{ overlay }}
    {% endif %}
</div>
<div class="label">
    {{ label }}
</div>
```

### Step 3: Create empty CSS file

`web/themes/custom/egam/components/entity_teaser/entity_teaser.css`:
```css
/* Styles handled by grid.css, artwork-hover.css, and Tailwind utilities */
```

### Step 4: Clear cache and verify SDC is registered

```bash
ddev drush cr
```
Expected: no errors. The SDC system auto-discovers components in `components/`.

### Step 5: Commit

```bash
git add web/themes/custom/egam/components/entity_teaser/
git commit -m "feat(theme): add entity_teaser SDC"
```

---

## Task 2: Update teaser templates to use `entity_teaser` SDC

**Files to modify:**
- `web/themes/custom/egam/templates/content/artwork/artwork--teaser.html.twig`
- `web/themes/custom/egam/templates/content/artist/artist--teaser.html.twig`
- `web/themes/custom/egam/templates/content/game/game--teaser.html.twig`
- `web/themes/custom/egam/templates/content/museum/museum--teaser.html.twig`
- `web/themes/custom/egam/templates/content/screenshot/screenshot--teaser.html.twig`

### Step 1: Update `artwork--teaser.html.twig`

Replace entire file content:
```twig
{#
/**
 * @file
 * Theme implementation to present an artwork entity in teaser view.
 */
#}
<article{{ attributes.addClass('artwork', view_mode) }}>
    {% if view_mode != 'full' %}
        {{ title_prefix }}
        {{ title_suffix }}
    {% endif %}
    {% if content %}
        {% set cover_content %}
            {% if content.field_thumbnail.0 is not empty %}
                {{ content.field_thumbnail }}
            {% else %}
                {{ content.field_cover }}
            {% endif %}
        {% endset %}
        {{ include('egam:entity_teaser', {
            cover: cover_content,
            label: full_title|raw,
        }) }}
    {% endif %}
</article>
```

### Step 2: Update `artist--teaser.html.twig`

Replace entire file content:
```twig
{#
/**
 * @file
 * Theme implementation to present an artist entity in teaser view.
 */
#}
<article{{ attributes.addClass('artist', view_mode) }}>
    {% if view_mode != 'full' %}
        {{ title_prefix }}
        {{ title_suffix }}
    {% endif %}
    {% if content %}
        {% set cover_content %}
            {% if content.field_thumbnail.0 is not empty %}
                {{ content.field_thumbnail }}
            {% else %}
                {{ content.field_cover }}
            {% endif %}
        {% endset %}
        {{ include('egam:entity_teaser', {
            cover: cover_content,
            label: content.label,
        }) }}
    {% endif %}
</article>
```

### Step 3: Update `game--teaser.html.twig`

Replace entire file content:
```twig
{#
/**
 * @file
 * Theme implementation to present a game entity in teaser view.
 */
#}
<article{{ attributes.addClass('game', view_mode) }}>
    {% if view_mode != 'full' %}
        {{ title_prefix }}
        {{ title_suffix }}
    {% endif %}
    {% if content %}
        {{ include('egam:entity_teaser', {
            cover: content.field_cover,
            label: full_title|raw,
        }) }}
    {% endif %}
</article>
```

### Step 4: Update `museum--teaser.html.twig`

Replace entire file content:
```twig
{#
/**
 * @file
 * Theme implementation to present a museum entity in teaser view.
 */
#}
<article{{ attributes.addClass('museum', view_mode) }}>
    {% if view_mode != 'full' %}
        {{ title_prefix }}
        {{ title_suffix }}
    {% endif %}
    {% if content %}
        {{ include('egam:entity_teaser', {
            cover: content.field_cover,
            label: content.label,
        }) }}
    {% endif %}
</article>
```

### Step 5: Update `screenshot--teaser.html.twig`

The screenshot teaser has an artwork-hover overlay. The `.artwork-hover-container` with Alpine.js must wrap both the image and the overlay. We pass this whole structure as the `cover` slot.

Replace entire file content:
```twig
{#
/**
 * @file
 * Theme implementation to present a screenshot entity in teaser view.
 */
#}
<article{{ attributes.addClass('screenshot', 'w-60', view_mode) }}>
    {% if view_mode != 'full' %}
        {{ title_prefix }}
        {{ title_suffix }}
    {% endif %}
    {% if content %}
        {% set cover_content %}
            {% if artworks %}
                <div class="artwork-hover-container" x-data="{ showArtworks: false }" @mouseenter="showArtworks = true" @mouseleave="showArtworks = false">
                    <a href="{{ content.label.0['#url'] }}" class="cover-link">
                        {% if content.field_thumbnail.0 is not empty %}
                            {{ content.field_thumbnail }}
                        {% else %}
                            {{ content.field_cover }}
                        {% endif %}
                    </a>
                    <div class="artwork-overlay" x-show="showArtworks" x-transition:enter="artwork-overlay-enter" x-transition:enter-start="artwork-overlay-enter-start" x-transition:enter-end="artwork-overlay-enter-end" x-transition:leave="artwork-overlay-leave" x-transition:leave-start="artwork-overlay-leave-start" x-transition:leave-end="artwork-overlay-leave-end" style="display: none;">
                        <ul class="artwork-list">
                            {% for artwork in artworks %}
                                <li class="artwork-item">
                                    <span class="artwork-title">{{ artwork.title }}</span>
                                    <span class="artwork-artist">{{ artwork.artist }}</span>
                                </li>
                            {% endfor %}
                        </ul>
                    </div>
                </div>
            {% else %}
                {% if content.field_thumbnail.0 is not empty %}
                    {{ content.field_thumbnail }}
                {% else %}
                    {{ content.field_cover }}
                {% endif %}
            {% endif %}
        {% endset %}
        {{ include('egam:entity_teaser', {
            cover: cover_content,
            label: '<a href="' ~ content.label.0['#url'] ~ '">' ~ (context_title|default(screenshot.label)|e) ~ '</a>',
        }) }}
    {% endif %}
</article>
```

**Note on screenshot label:** The label is built as a raw HTML string using Twig's `~` concat and `|e` escape filter. The `|raw` filter is NOT applied in the SDC template — the entity template is responsible for passing safe HTML.

Actually, the SDC uses `{{ label }}` which auto-escapes. To pass a link, the label must be marked raw. Update the screenshot teaser label line:
```twig
label: ('<a href="' ~ content.label.0['#url'] ~ '">' ~ (context_title|default(screenshot.label)|e) ~ '</a>')|raw,
```

### Step 6: Clear cache and visually verify

```bash
ddev drush cr
```

Navigate to:
- `/artworks` — verify artwork teaser cards render correctly
- `/games` — verify game teaser cards
- `/artists` — verify artist teaser cards
- `/museums` — verify museum teaser cards
- `/screenshots` — verify screenshot teaser cards with artwork-hover overlay

Expected: All grids look identical to before. No visual regressions.

### Step 7: Commit

```bash
git add web/themes/custom/egam/templates/content/
git commit -m "refactor(theme): use entity_teaser SDC in all teaser templates"
```

---

## Task 3: Fix `illustration_container` SDC — add `cover` slot

Currently `illustration_container.twig` line 10 hardcodes `{{ content.field_cover }}`. This means the component implicitly relies on the parent template's scope rather than using a proper slot.

**Files to modify:**
- `web/themes/custom/egam/components/illustration_container/illustration_container.component.yml`
- `web/themes/custom/egam/components/illustration_container/illustration_container.twig`
- `web/themes/custom/egam/templates/content/artwork/artwork.html.twig`
- `web/themes/custom/egam/templates/content/game/game.html.twig`
- `web/themes/custom/egam/templates/content/artist/artist.html.twig`
- `web/themes/custom/egam/templates/content/museum/museum.html.twig`
- `web/themes/custom/egam/templates/content/screenshot/screenshot.html.twig`

### Step 1: Add `cover` slot to component definition

Replace `illustration_container.component.yml`:
```yaml
name: Illustration Container
slots:
  cover:
    title: Cover
    description: Rendered cover image field
  title:
    title: Title
    description: Content title
  notes:
    title: Notes
    description: Illustration description
```

### Step 2: Update component template to use the slot

In `illustration_container.twig`, change line 10 from `{{ content.field_cover }}` to `{{ cover }}`:

```twig
<div class="illustration-container">
    <div class="cover lightbox">
        {% if title is not empty %}
            <div class="title md:hidden">
                <div class="item name">
                    <h1>{{ title }}</h1>
                </div>
            </div>
        {% endif %}
        {{ cover }}
    </div>
    {% if notes.0 %}
        {% if show_notes_heading is not same as(false) %}
            <div class="item description">
                <h2>{{ 'Notes'|t }}</h2>
                <div>{{ notes }}</div>
            </div>
        {% else %}
            <p class="mt-2">{{ notes }}</p>
        {% endif %}
    {% endif %}
</div>
```

### Step 3: Update all entity full-page templates to pass `cover`

In each full-page template, find the `include('egam:illustration_container', {...})` call and add `cover: content.field_cover`.

**`artwork.html.twig`** — change:
```twig
{{ include('egam:illustration_container', {
    title: content.label,
    notes: content.description,
}) }}
```
to:
```twig
{{ include('egam:illustration_container', {
    cover: content.field_cover,
    title: content.label,
    notes: content.description,
}) }}
```

**`game.html.twig`** — change:
```twig
{{ include('egam:illustration_container', {
    title: content.label,
    notes: content.description,
}) }}
```
to:
```twig
{{ include('egam:illustration_container', {
    cover: content.field_cover,
    title: content.label,
    notes: content.description,
}) }}
```

**`artist.html.twig`** — change:
```twig
{{ include('egam:illustration_container', {
    title: content.label,
    notes: content.field_cover_caption,
    show_notes_heading: false,
}) }}
```
to:
```twig
{{ include('egam:illustration_container', {
    cover: content.field_cover,
    title: content.label,
    notes: content.field_cover_caption,
    show_notes_heading: false,
}) }}
```

**`museum.html.twig`** — change:
```twig
{{ include('egam:illustration_container', {
    title: content.label,
}) }}
```
to:
```twig
{{ include('egam:illustration_container', {
    cover: content.field_cover,
    title: content.label,
}) }}
```

**`screenshot.html.twig`** — change:
```twig
{{ include('egam:illustration_container', {
    notes: content.description,
}) }}
```
to:
```twig
{{ include('egam:illustration_container', {
    cover: content.field_cover,
    notes: content.description,
}) }}
```

### Step 4: Clear cache and verify full entity pages

```bash
ddev drush cr
```

Navigate to any entity canonical page (e.g. an artwork, a game, an artist). Verify the cover image still renders in the illustration container.

### Step 5: Commit

```bash
git add web/themes/custom/egam/components/illustration_container/ web/themes/custom/egam/templates/content/
git commit -m "fix(theme): add cover slot to illustration_container SDC"
```

---

## Task 4: Create `info-rows.html.twig` macro file

A Twig macro file that provides helpers for rendering info table rows, reducing boilerplate in entity full-page templates.

**Files:**
- Create: `web/themes/custom/egam/templates/macros/info-rows.html.twig`
- Modify: `templates/content/artwork/artwork.html.twig`
- Modify: `templates/content/game/game.html.twig`
- Modify: `templates/content/artist/artist.html.twig`
- Modify: `templates/content/museum/museum.html.twig`
- Modify: `templates/content/screenshot/screenshot.html.twig`

### Step 1: Create macro file

`web/themes/custom/egam/templates/macros/info-rows.html.twig`:
```twig
{#
/**
 * @file
 * Twig macros for rendering info table rows in entity detail pages.
 *
 * Usage:
 *   {% import '@egam/macros/info-rows.html.twig' as rows %}
 *
 *   {% if content.field_date.0 %}
 *     {{ rows.info_row('Date'|t, content.field_date, 'date') }}
 *   {% endif %}
 */
#}

{# Standard row: label + Drupal rendered field value #}
{% macro info_row(label, value, css_class) %}
<tr class="item {{ css_class }}">
    <th scope="row">{{ label }}</th>
    <td>{{ value }}</td>
</tr>
{% endmacro %}

{# Raw row: label + pre-rendered HTML value (e.g. full_artist|raw from preprocess) #}
{% macro info_row_raw(label, value, css_class) %}
<tr class="item {{ css_class }}">
    <th scope="row">{{ label }}</th>
    <td>{{ value|raw }}</td>
</tr>
{% endmacro %}

{# Heading-only row: for field_more_info which renders in the th cell spanning both columns #}
{% macro info_row_heading(content) %}
<tr class="item more_info">
    <th scope="row">{{ content }}</th>
</tr>
{% endmacro %}
```

### Step 2: Update `artwork.html.twig` to use macros

The table section of `artwork.html.twig` (lines 36–86) becomes:

```twig
{% import '@egam/macros/info-rows.html.twig' as rows %}
...
<table class="infos">
    {% if content.field_original_title.0 %}
        {{ rows.info_row('Original title'|t, content.field_original_title, 'original_title') }}
    {% endif %}
    {% if content.field_alternative_title.0 %}
        {{ rows.info_row('Alternative title'|t, content.field_alternative_title, 'alternative_title') }}
    {% endif %}
    {% if content.field_artist.0 %}
        {{ rows.info_row_raw('Artist'|t, full_artist, 'artist') }}
    {% endif %}
    {% if content.field_date.0 %}
        {{ rows.info_row('Date'|t, content.field_date, 'date') }}
    {% endif %}
    {% if content.field_medium.0 %}
        {{ rows.info_row('Technique'|t, content.field_medium, 'medium') }}
    {% endif %}
    {% if content.field_dimensions.0 %}
        {{ rows.info_row('Dimensions'|t, content.field_dimensions, 'dimensions') }}
    {% endif %}
    {% if full_location_link %}
        {{ rows.info_row_raw('Conservation location'|t, full_location_link, 'museum') }}
    {% endif %}
    {% if content.field_more_info.0 %}
        {{ rows.info_row_heading(content.field_more_info) }}
    {% endif %}
</table>
```

### Step 3: Update `game.html.twig` to use macros

```twig
{% import '@egam/macros/info-rows.html.twig' as rows %}
...
<table class="infos">
    {% if content.field_date.0 %}
        {{ rows.info_row('Date'|t, content.field_date, 'date') }}
    {% endif %}
    {% if content.field_developer.0 %}
        {{ rows.info_row('Développeur'|t, content.field_developer, 'developer') }}
    {% endif %}
    {% if content.field_editor.0 %}
        {{ rows.info_row('Éditeur'|t, content.field_editor, 'editor') }}
    {% endif %}
    {% if content.field_more_info.0 %}
        {{ rows.info_row_heading(content.field_more_info) }}
    {% endif %}
</table>
```

### Step 4: Update `artist.html.twig` to use macros

Also fix the existing bug on line 47 where `<td>` has no closing tag (`<td>` instead of `</td>`).

```twig
{% import '@egam/macros/info-rows.html.twig' as rows %}
...
<table class="infos">
    {% if content.field_full_name.0 %}
        {{ rows.info_row('Full name'|t, content.field_full_name, 'full_name') }}
    {% endif %}
    {% if content.field_nickname.0 %}
        {{ rows.info_row('Nickname'|t, content.field_nickname, 'nickname') }}
    {% endif %}
    {% if content.field_nationality.0 %}
        {{ rows.info_row('Nationality'|t, content.field_nationality, 'nationality') }}
    {% endif %}
    {% if content.field_activity.0 %}
        {{ rows.info_row('Activity'|t, content.field_activity, 'activity') }}
    {% endif %}
    {% if content.field_birth_year.0 %}
        {{ rows.info_row('Birth year'|t, content.field_birth_year, 'birth_year') }}
    {% endif %}
    {% if content.field_death_year.0 %}
        {{ rows.info_row('Death year'|t, content.field_death_year, 'death_year') }}
    {% endif %}
    {% if content.description.0 %}
        {{ rows.info_row('Notes'|t, content.description, 'description') }}
    {% endif %}
    {% if content.field_more_info.0 %}
        {{ rows.info_row_heading(content.field_more_info) }}
    {% endif %}
</table>
```

### Step 5: Update `museum.html.twig` to use macros

```twig
{% import '@egam/macros/info-rows.html.twig' as rows %}
...
<table class="infos">
    {% if content.field_location.0 %}
        {{ rows.info_row('Location'|t, content.field_location, 'location') }}
    {% endif %}
    {% if content.field_foundation_date.0 %}
        {{ rows.info_row('Open in'|t, content.field_foundation_date, 'foundation_date') }}
    {% endif %}
    {% if content.field_website.0 %}
        {{ rows.info_row('Website'|t, content.field_website, 'website') }}
    {% endif %}
    {% if content.description.0 %}
        {{ rows.info_row('Notes'|t, content.description, 'description') }}
    {% endif %}
    {% if content.field_more_info.0 %}
        {{ rows.info_row_heading(content.field_more_info) }}
    {% endif %}
</table>
```

### Step 6: Update `screenshot.html.twig` to use macros

Screenshot has special fields (game_date, game_developer, game_editor preprocessed vars) and a special swiper row. Use macros for the standard rows only.

```twig
{% import '@egam/macros/info-rows.html.twig' as rows %}
...
<table class="infos">
    {% if content.field_game.0 %}
        {{ rows.info_row('Game'|t, content.field_game, 'game') }}
    {% endif %}
    {% if game_date %}
        {{ rows.info_row('Date'|t, game_date, 'date') }}
    {% endif %}
    {% if game_developer %}
        <tr class="item developer">
            <th scope="row">{{ 'Developer'|t }}</th>
            {# Currently no link - [0]['#title'] to remove when taxonomy page is live #}
            <td>{{ game_developer[0]['#title'] }}</td>
        </tr>
    {% endif %}
    {% if game_editor %}
        <tr class="item editor">
            <th scope="row">{{ 'Editor'|t }}</th>
            {# Currently no link - [0]['#title'] to remove when taxonomy page is live #}
            <td>{{ game_editor[0]['#title'] }}</td>
        </tr>
    {% endif %}
    {% if content.field_more_info.0 %}
        {{ rows.info_row_heading(content.field_more_info) }}
    {% endif %}
    <tr class="item swiper-container main-swiper">
        <th scope="row" class="swiper-slide"></th>
    </tr>
</table>
```

### Step 7: Clear cache and verify all entity detail pages

```bash
ddev drush cr
```

Navigate to at least one canonical page of each entity type and verify the info table renders correctly.

### Step 8: Commit

```bash
git add web/themes/custom/egam/templates/macros/ web/themes/custom/egam/templates/content/
git commit -m "refactor(theme): add info-rows macros, use in all entity detail templates"
```

---

## Task 5: Create `related-content` partial

The `.container.related-content` block is identical in all 5 entity full-page templates (only `title`, `items`, and `items_class` vary).

**Files:**
- Create: `web/themes/custom/egam/templates/partials/related-content.html.twig`
- Modify: `templates/content/artwork/artwork.html.twig`
- Modify: `templates/content/game/game.html.twig`
- Modify: `templates/content/artist/artist.html.twig`
- Modify: `templates/content/museum/museum.html.twig`
- Modify: `templates/content/screenshot/screenshot.html.twig`

### Step 1: Create the partial

`web/themes/custom/egam/templates/partials/related-content.html.twig`:
```twig
{#
/**
 * @file
 * Partial template for the related content section shown below entity detail pages.
 *
 * Variables:
 * - title: Translated heading string
 * - items: Rendered related entity items
 * - items_class: CSS class for the items container (e.g. 'games', 'artworks', 'screenshots')
 */
#}
<div class="container related-content">
    <h2>{{ title }}</h2>
    <div class="items {{ items_class }}">
        {{ items }}
    </div>
</div>
```

### Step 2: Update `artwork.html.twig` — replace related-content block

Replace:
```twig
{% if related_games is not empty %}
    <div class="container related-content">
        <h2>{{ multiple_content ? 'Cette œuvre apparait dans les jeux suivants :'|t : 'Cette œuvre apparait dans le jeu suivant :'|t }}</h2>
        <div class="items games">
            {{ related_games }}
        </div>
    </div>
{% endif %}
```

With:
```twig
{% if related_games is not empty %}
    {{ include('@egam/partials/related-content.html.twig', {
        title: multiple_content ? 'Cette œuvre apparait dans les jeux suivants :'|t : 'Cette œuvre apparait dans le jeu suivant :'|t,
        items: related_games,
        items_class: 'games',
    }) }}
{% endif %}
```

### Step 3: Update `game.html.twig` — replace related-content block

Replace:
```twig
{% if related_artworks is not empty %}
    <div class="container related-content">
        <h2>{{ multiple_content ? 'Œuvres citées dans le jeu :'|t : 'Œuvre citée dans le jeu :'|t }}</h2>
        <div class="items screenshots">
            {{ related_artworks }}
        </div>
    </div>
{% endif %}
```

With:
```twig
{% if related_artworks is not empty %}
    {{ include('@egam/partials/related-content.html.twig', {
        title: multiple_content ? 'Œuvres citées dans le jeu :'|t : 'Œuvre citée dans le jeu :'|t,
        items: related_artworks,
        items_class: 'screenshots',
    }) }}
{% endif %}
```

### Step 4: Update `artist.html.twig` — replace related-content block

Replace:
```twig
{% if related_artworks is not empty %}
    <div class="container related-content">
        <h2>{{ multiple_content ? 'Œuvres citées dans des jeux vidéo :'|t : 'Œuvre citée dans des jeux vidéo :'|t}}</h2>
        <div class="items artworks">
            {{ related_artworks }}
        </div>
    </div>
{% endif %}
```

With:
```twig
{% if related_artworks is not empty %}
    {{ include('@egam/partials/related-content.html.twig', {
        title: multiple_content ? 'Œuvres citées dans des jeux vidéo :'|t : 'Œuvre citée dans des jeux vidéo :'|t,
        items: related_artworks,
        items_class: 'artworks',
    }) }}
{% endif %}
```

### Step 5: Update `museum.html.twig` — replace related-content block

Replace:
```twig
{% if related_artworks is not empty %}
    <div class="container related-content">
        <h2>{{ multiple_content ? 'Œuvres du musée citées dans des jeux vidéo :'|t : 'Œuvre du musée citée dans des jeux vidéo :'|t }}</h2>
        <div class="items artworks">
            {{ related_artworks }}
        </div>
    </div>
{% endif %}
```

With:
```twig
{% if related_artworks is not empty %}
    {{ include('@egam/partials/related-content.html.twig', {
        title: multiple_content ? 'Œuvres du musée citées dans des jeux vidéo :'|t : 'Œuvre du musée citée dans des jeux vidéo :'|t,
        items: related_artworks,
        items_class: 'artworks',
    }) }}
{% endif %}
```

### Step 6: Update `screenshot.html.twig` — replace related-content block

Replace:
```twig
{% if content.field_artwork.0 %}
    <div class="container related-content">
        <h2>{{ multiple_related_artworks ? 'Œuvres présentes dans cette image :'|t : 'Œuvre présente dans cette image :'|t }}</h2>
        <div class="items artworks">
            {{ content.field_artwork }}
        </div>
    </div>
{% endif %}
```

With:
```twig
{% if content.field_artwork.0 %}
    {{ include('@egam/partials/related-content.html.twig', {
        title: multiple_related_artworks ? 'Œuvres présentes dans cette image :'|t : 'Œuvre présente dans cette image :'|t,
        items: content.field_artwork,
        items_class: 'artworks',
    }) }}
{% endif %}
```

### Step 7: Clear cache and verify

```bash
ddev drush cr
```

Navigate to entity canonical pages and verify the related-content section still renders with the correct heading and items.

### Step 8: Commit

```bash
git add web/themes/custom/egam/templates/partials/ web/themes/custom/egam/templates/content/
git commit -m "refactor(theme): extract related-content section into Twig partial"
```

---

## Task 6: Create `entity-detail-layout` partial

Extracts the shared `.content.{type}` wrapper (illustration_container + .details) from artwork, game, artist, museum templates. Screenshot is excluded — it has a different structure (no `.title hidden md:block` div, has a special swiper row).

**Files:**
- Create: `web/themes/custom/egam/templates/partials/entity-detail-layout.html.twig`
- Modify: `templates/content/artwork/artwork.html.twig`
- Modify: `templates/content/game/game.html.twig`
- Modify: `templates/content/artist/artist.html.twig`
- Modify: `templates/content/museum/museum.html.twig`

### Step 1: Create the partial

`web/themes/custom/egam/templates/partials/entity-detail-layout.html.twig`:
```twig
{#
/**
 * @file
 * Partial template for the entity detail layout (illustration + info table).
 * Used by artwork, game, artist, and museum full-page templates.
 * Not used by screenshot (different structure).
 *
 * Variables:
 * - entity_type: CSS class suffix ('artwork', 'game', 'artist', 'museum')
 * - label: Rendered label field (for illustration title and .title heading)
 * - cover: Rendered cover image field
 * - notes: Rendered notes/description field (optional)
 * - show_notes_heading: Boolean, whether to show the Notes heading (default: true)
 * - info_rows: Captured HTML for the <table> rows (use {% set info_rows %}...{% endset %})
 */
#}
<div class="content {{ entity_type }}">
    {{ include('egam:illustration_container', {
        cover: cover,
        title: label,
        notes: notes|default(null),
        show_notes_heading: show_notes_heading is defined ? show_notes_heading : true,
    }) }}
    <div class="details">
        <div class="title hidden md:block">
            {% if label %}
                <div class="item name">
                    <h1>{{ label }}</h1>
                </div>
            {% endif %}
        </div>
        <table class="infos">
            {{ info_rows|raw }}
        </table>
    </div>
</div>
```

**Note on `info_rows|raw`:** The `info_rows` variable is a captured Twig block (`{% set info_rows %}...{% endset %}`). Twig captures this as a pre-rendered string, so `|raw` is needed to output it without double-escaping. The content was already escaped during capture.

### Step 2: Refactor `artwork.html.twig`

Full file after refactor:
```twig
{#
/**
 * @file
 * Default theme implementation to present an artwork entity.
 */
#}
<article{{ attributes }}>
    {% if view_mode != 'full' %}
        {{ title_prefix }}
        {{ title_suffix }}
    {% endif %}
    {% if content %}
        {% import '@egam/macros/info-rows.html.twig' as rows %}
        {% set info_rows %}
            {% if content.field_original_title.0 %}
                {{ rows.info_row('Original title'|t, content.field_original_title, 'original_title') }}
            {% endif %}
            {% if content.field_alternative_title.0 %}
                {{ rows.info_row('Alternative title'|t, content.field_alternative_title, 'alternative_title') }}
            {% endif %}
            {% if content.field_artist.0 %}
                {{ rows.info_row_raw('Artist'|t, full_artist, 'artist') }}
            {% endif %}
            {% if content.field_date.0 %}
                {{ rows.info_row('Date'|t, content.field_date, 'date') }}
            {% endif %}
            {% if content.field_medium.0 %}
                {{ rows.info_row('Technique'|t, content.field_medium, 'medium') }}
            {% endif %}
            {% if content.field_dimensions.0 %}
                {{ rows.info_row('Dimensions'|t, content.field_dimensions, 'dimensions') }}
            {% endif %}
            {% if full_location_link %}
                {{ rows.info_row_raw('Conservation location'|t, full_location_link, 'museum') }}
            {% endif %}
            {% if content.field_more_info.0 %}
                {{ rows.info_row_heading(content.field_more_info) }}
            {% endif %}
        {% endset %}
        {{ include('@egam/partials/entity-detail-layout.html.twig', {
            entity_type: 'artwork',
            label: content.label,
            cover: content.field_cover,
            notes: content.description,
            info_rows: info_rows,
        }) }}
        {% if related_games is not empty %}
            {{ include('@egam/partials/related-content.html.twig', {
                title: multiple_content ? 'Cette œuvre apparait dans les jeux suivants :'|t : 'Cette œuvre apparait dans le jeu suivant :'|t,
                items: related_games,
                items_class: 'games',
            }) }}
        {% endif %}
    {% endif %}
</article>
```

### Step 3: Refactor `game.html.twig`

Full file after refactor:
```twig
{#
/**
 * @file
 * Default theme implementation to present a game entity.
 */
#}
<article{{ attributes }}>
    {% if view_mode != 'full' %}
        {{ title_prefix }}
        {{ title_suffix }}
    {% endif %}
    {% if content %}
        {% import '@egam/macros/info-rows.html.twig' as rows %}
        {% set info_rows %}
            {% if content.field_date.0 %}
                {{ rows.info_row('Date'|t, content.field_date, 'date') }}
            {% endif %}
            {% if content.field_developer.0 %}
                {{ rows.info_row('Développeur'|t, content.field_developer, 'developer') }}
            {% endif %}
            {% if content.field_editor.0 %}
                {{ rows.info_row('Éditeur'|t, content.field_editor, 'editor') }}
            {% endif %}
            {% if content.field_more_info.0 %}
                {{ rows.info_row_heading(content.field_more_info) }}
            {% endif %}
        {% endset %}
        {{ include('@egam/partials/entity-detail-layout.html.twig', {
            entity_type: 'game',
            label: content.label,
            cover: content.field_cover,
            notes: content.description,
            info_rows: info_rows,
        }) }}
        {% if related_artworks is not empty %}
            {{ include('@egam/partials/related-content.html.twig', {
                title: multiple_content ? 'Œuvres citées dans le jeu :'|t : 'Œuvre citée dans le jeu :'|t,
                items: related_artworks,
                items_class: 'screenshots',
            }) }}
        {% endif %}
    {% endif %}
</article>
```

### Step 4: Refactor `artist.html.twig`

Full file after refactor:
```twig
{#
/**
 * @file
 * Default theme implementation to present an artist entity.
 */
#}
<article{{ attributes }}>
    {% if view_mode != 'full' %}
        {{ title_prefix }}
        {{ title_suffix }}
    {% endif %}
    {% if content %}
        {% import '@egam/macros/info-rows.html.twig' as rows %}
        {% set info_rows %}
            {% if content.field_full_name.0 %}
                {{ rows.info_row('Full name'|t, content.field_full_name, 'full_name') }}
            {% endif %}
            {% if content.field_nickname.0 %}
                {{ rows.info_row('Nickname'|t, content.field_nickname, 'nickname') }}
            {% endif %}
            {% if content.field_nationality.0 %}
                {{ rows.info_row('Nationality'|t, content.field_nationality, 'nationality') }}
            {% endif %}
            {% if content.field_activity.0 %}
                {{ rows.info_row('Activity'|t, content.field_activity, 'activity') }}
            {% endif %}
            {% if content.field_birth_year.0 %}
                {{ rows.info_row('Birth year'|t, content.field_birth_year, 'birth_year') }}
            {% endif %}
            {% if content.field_death_year.0 %}
                {{ rows.info_row('Death year'|t, content.field_death_year, 'death_year') }}
            {% endif %}
            {% if content.description.0 %}
                {{ rows.info_row('Notes'|t, content.description, 'description') }}
            {% endif %}
            {% if content.field_more_info.0 %}
                {{ rows.info_row_heading(content.field_more_info) }}
            {% endif %}
        {% endset %}
        {{ include('@egam/partials/entity-detail-layout.html.twig', {
            entity_type: 'artist',
            label: content.label,
            cover: content.field_cover,
            notes: content.field_cover_caption,
            show_notes_heading: false,
            info_rows: info_rows,
        }) }}
        {% if related_artworks is not empty %}
            {{ include('@egam/partials/related-content.html.twig', {
                title: multiple_content ? 'Œuvres citées dans des jeux vidéo :'|t : 'Œuvre citée dans des jeux vidéo :'|t,
                items: related_artworks,
                items_class: 'artworks',
            }) }}
        {% endif %}
    {% endif %}
</article>
```

### Step 5: Refactor `museum.html.twig`

Full file after refactor:
```twig
{#
/**
 * @file
 * Default theme implementation to present a museum entity.
 */
#}
<article{{ attributes }}>
    {% if view_mode != 'full' %}
        {{ title_prefix }}
        {{ title_suffix }}
    {% endif %}
    {% if content %}
        {% import '@egam/macros/info-rows.html.twig' as rows %}
        {% set info_rows %}
            {% if content.field_location.0 %}
                {{ rows.info_row('Location'|t, content.field_location, 'location') }}
            {% endif %}
            {% if content.field_foundation_date.0 %}
                {{ rows.info_row('Open in'|t, content.field_foundation_date, 'foundation_date') }}
            {% endif %}
            {% if content.field_website.0 %}
                {{ rows.info_row('Website'|t, content.field_website, 'website') }}
            {% endif %}
            {% if content.description.0 %}
                {{ rows.info_row('Notes'|t, content.description, 'description') }}
            {% endif %}
            {% if content.field_more_info.0 %}
                {{ rows.info_row_heading(content.field_more_info) }}
            {% endif %}
        {% endset %}
        {{ include('@egam/partials/entity-detail-layout.html.twig', {
            entity_type: 'museum',
            label: content.label,
            cover: content.field_cover,
            notes: content.description,
            info_rows: info_rows,
        }) }}
        {% if related_artworks is not empty %}
            {{ include('@egam/partials/related-content.html.twig', {
                title: multiple_content ? 'Œuvres du musée citées dans des jeux vidéo :'|t : 'Œuvre du musée citée dans des jeux vidéo :'|t,
                items: related_artworks,
                items_class: 'artworks',
            }) }}
        {% endif %}
    {% endif %}
</article>
```

### Step 6: Clear cache and run full visual verification

```bash
ddev drush cr
```

Check all entity detail pages:
- `/artwork/{id}` — illustration + info table + related games
- `/game/{id}` — illustration + info table + related artworks
- `/artist/{id}` — illustration + caption (no heading) + info table + related artworks
- `/museum/{id}` — illustration + info table + related artworks

Verify nothing is visually broken (layout, fonts, related-content section).

### Step 7: Commit

```bash
git add web/themes/custom/egam/templates/partials/ web/themes/custom/egam/templates/content/
git commit -m "refactor(theme): extract entity-detail-layout partial, refactor artwork/game/artist/museum templates"
```

---

## Task 7: Final verification

### Step 1: Clear cache one final time

```bash
ddev drush cr
```

### Step 2: Check all collection pages (grids)

- `/artworks`
- `/games`
- `/artists`
- `/museums`
- `/screenshots`

Verify: teaser cards render correctly, artwork-hover works on screenshot cards, images display.

### Step 3: Check all entity detail pages

Visit at least one of each type and verify full page rendering.

### Step 4: Commit summary

```bash
git log --oneline -6
```

Expected output (5 commits from this plan):
```
abc1234 refactor(theme): extract entity-detail-layout partial, refactor artwork/game/artist/museum templates
def5678 refactor(theme): extract related-content section into Twig partial
ghi9012 refactor(theme): add info-rows macros, use in all entity detail templates
jkl3456 fix(theme): add cover slot to illustration_container SDC
mno7890 refactor(theme): use entity_teaser SDC in all teaser templates
pqr1234 feat(theme): add entity_teaser SDC
```

---

## Rollback Note

Each task is committed independently. If any task causes a regression, revert with:
```bash
git revert HEAD
ddev drush cr
```
