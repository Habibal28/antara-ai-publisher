# Architecture

## 1. High Level

                    ┌──────────────┐
                    │    ANTARA    │
                    └──────┬───────┘
                           │
                           ▼
                    ┌──────────────┐
                    │   Playwright │
                    └──────┬───────┘
                           │
                           ▼
                    ┌──────────────┐
                    │ AntaraService│
                    └──────┬───────┘
                           │
                           ▼
                    ┌──────────────┐
                    │    MySQL     │
                    │   Articles   │
                    └──────┬───────┘
                           │
                           ▼
                    ┌──────────────┐
                    │  AIService   │
                    │    Gemini    │
                    └──────┬───────┘
                           │
                           ▼
                    ┌──────────────┐
                    │   Telegram   │
                    │   Approval   │
                    └──────┬───────┘
                           │
                     APPROVED
                           │
                           ▼
                    ┌──────────────┐
                    │ WordPress API│
                    └──────────────┘

---

# 2. Application Structure

Laravel:

app/
├── Models/
├── Services/
├── Jobs/
├── Console/
└── Http/

External integrations:

AntaraService
AIService
TelegramService
WordPressService

---

# 3. Article Domain

Model:

`App\Models\Article`

Database table:

`articles`

Article lifecycle:

pending
→ scraped
→ processing
→ drafted
→ waiting_approval
→ approved
→ publishing
→ published

Failure:

→ failed

Status names may be adjusted if implementation requires it,
but state transitions must remain explicit.

---

# 4. Article Database

Current fields:

-   id
-   source
-   source_id
-   source_url
-   source_url_hash
-   source_title
-   source_content
-   source_published_at
-   content_hash
-   facts
-   rewritten_title
-   rewritten_content
-   ai_model
-   ai_processed_at
-   status
-   telegram_message_id
-   wordpress_post_id
-   error_message
-   approved_at
-   published_at
-   created_at
-   updated_at

---

# 5. Deduplication

## Level 1

Check:

`source + source_id`

## Level 2

Check:

`source + source_url_hash`

URL hash:

SHA-256

## Level 3

Check:

`content_hash`

Content hash:

SHA-256

Semantic similarity is NOT part of MVP.

---

# 6. ANTARA Integration

Playwright handles browser interaction.

Responsibilities:

### List scraping

Get:

-   article URL
-   article ID
-   article title if available

### Article scraping

Get:

-   source ID
-   title
-   date
-   content
-   image URL

AntaraService should normalize the result into a consistent
application data structure.

Example:

```php
[
    'source' => 'antara',
    'source_id' => '13453476',
    'source_url' => '...',
    'source_title' => '...',
    'source_content' => '...',
    'source_published_at' => '...',
    'image_url' => '...',
]
```
