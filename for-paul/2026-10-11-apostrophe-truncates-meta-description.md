# Straight apostrophe in `description:` truncates the meta description

**What Susan was doing:** editing the `bill-at-normandy` page and giving it a new `description:` ("Uncle Bill's last weeks in England…").

**Symptom:** the built page had

```html
<meta name='description' 				content='Uncle Bill's last weeks in England before Normandy: …'>
```

The attribute is single-quoted, so the straight apostrophe ends it: browsers and search engines read the description as just `Uncle Bill`. The same happens in the `og:description` line. No build warning.

**Where:** `guts/template-post.php:26` (`content='{$description}'`), plus the `og:description` line. The description value isn't escaped before it's interpolated.

**How widespread:** at least 10 posts have a straight `'` in their `description:` line, e.g. `uncle-bill`, every `bill-at-*` page, `bill-as-radar`, `bob-family-home-page`, `dad-bb-high-school` (`grep -l "description:.*'" posts/*`). The fallback description (first ~115 characters of the post) presumably has the same problem when the body contains an apostrophe.

**Workaround applied:** on `bill-at-normandy` only, I used a curly apostrophe (’) in the description. I'll do the same on the other pages as Susan and I review them one by one, but haven't touched them in bulk.

**Suggested fix:** escape the value with `htmlspecialchars($description, ENT_QUOTES)` where it's put into the template (and the `og:` / any other attribute uses), or switch those attributes to double quotes *and* escape. Escaping is the safer fix, since a `"` in a description would break double-quoted attributes the same way.
