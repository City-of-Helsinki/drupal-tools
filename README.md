# Drupal tools

Provides Drush commands to perform various tasks.

## Installation

- `composer require drupal/helfi_drupal_tools`

Add the following to your composer.json's `installer-paths`:

```
"drush/Commands/{$name}": [
  "type:drupal-drush"
]
```

## Platform update

### Usage

- `drush helfi:tools:update-platform`

This will:

- Check if `drush/helfi_drupal_tools` package is up-to-date
- Update/add files from [City-of-Helsinki/drupal-helfi-platform](https://github.com/City-of-Helsinki/drupal-helfi-platform) repository
- Attempts to update external packages
- Run the update hooks

### Self update

You should always update `drupal/helfi_drupal_tools` package before running the command:

- `composer update drupal/helfi_drupal_tools`

This check can be disabled by passing `--no-self-update` flag.

### Auto updated files

Certain files are deemed required and will always be updated.

See [::updateDefaultFiles() and ::addDefaultFiles()](/UpdateCommands.php) methods for an up-to-date list of these files.

Files can be ignored by creating a file called `.platform/ignore`. The file should contain one file per line.

For example, add something like this to your `ignore` file to never update `settings.php` or `Dockerfile` files:

```
public/sites/default/settings.php
docker/openshift/Dockerfile
```

This check can be bypassed with `--no-ignore-files` flag. This will update files regardless of `ignore` file.

### Update external tools

At the moment, these tools are updated automatically:
- https://github.com/druidfi/tools

This can be disabled by passing `--no-update-external-packages` flag.

### Update hooks

Running update hooks will create a file called `.platform/schema`. The file contains the schema version and should be committed to Git.

This can be disabled by passing `--no-run-migrations` flag.

## Database sync

Add `OC_PROJECT_NAME=hki-kanslia-{your-project-name}` to your `.env` file and run:

- `drush helfi:oc:get-dump`


## Contact

Slack: #helfi-drupal (http://helsinkicity.slack.com/)
