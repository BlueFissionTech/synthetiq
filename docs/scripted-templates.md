# Scripted Template Blocks

Scripted template blocks are an opt-in response rendering feature for controlled
dynamic text inside templates. The supported syntax is:

```text
{= input}
{= intent}
{= context.user.name}
{= upper(context.status.summary)}
```

The renderer does not execute arbitrary PHP, shell commands, or external
scripts. It resolves variable paths from a bounded template context and supports
only the built-in transforms below:

- `upper(path)`
- `lower(path)`
- `trim(path)`
- `capitalize(path)`

## Enable

```php
$ai->enableScriptedTemplates(true);
```

Scripted templates are disabled by default. When disabled, `{=...}` blocks are
left as literal template text.

## Context

Available paths are:

- `input`
- `intent`
- `context.*`

Missing variables and invalid expressions resolve to an empty string and are
reported through:

```php
$ai->scriptedTemplateDiagnostics();
```

The response envelope also includes diagnostics under `templates.scripted`.
