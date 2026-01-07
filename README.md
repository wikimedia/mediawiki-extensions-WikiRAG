# WikiRAG - Provides data for RAG (Retrieval-Augmented Generation) applications from the wiki

## Working principles

This extension has three parts:

- Data providers (`IPageDataProvider`/`IAttachmentProvider`/`IContextDataProvider` implementations) that provide various information about the page
  - `IAttachmentProvider` - subtype of DataProvider, that provides a concrete file to be uploaded to the index, 
  instead of just content
  - `IContextDataProvider` - non-page-bound data provider. Can provide any arbitrary data on the system as a whole
- Change observers (`IChangeObserver` implementations) that observe changes to the wiki and call data providers
to update their data
- Targets (`ITarget` implementations) that store the data provided by data providers

Data is not directly indexed to targets, but first scheduled to be processed by a job at regular intervals.
Change observers are normally responsible for detecting indexable changes and scheduling jobs to process. 
Depending on what changed, change observers will only schedule certain DataProviders to be executed.

Data is indexed in two ways:

- Complete indexing - normally done as a LoggedUpdateMaintenance job, this indexes all pages in the wiki on first
`update.php` call. It can be re-run at any time to re-index all pages. It will first clear all scheduled jobs and
then schedule jobs for all pages in the wiki. It will NOT clear all data from the target!

To manually execute a complete re-indexing, run:
```
php extensions/WikiRAG/src/Maintenance/ScheduleAll.php --force
```

- Incremental indexing - done using ChangeObservers which are responsible for detecting indexable
changes and scheduling jobs to process. Depending on what changed,
change observers will only schedule certain DataProviders to be executed.

Scheduling of jobs is done using `Scheduler` (`WikiRAG.Scheduler` service),
which handles storing scheduled jobs in the database and
storing the information of the runs once the jobs are processed. You can pass any page to the scheduler,
it will internally check if page is suitable for indexing (see [which pages are indexed](#types-of-pages-being-indexed) for more info),
and skip the page if it's not suitable. When scheduling, list of `DataProviders` is also passed (key => instance pairs),
in order to schedule only those `DataProviders` that are actually needed. If called multiple times for the same page,
data provider list will be automatically merged.
Note: For performance reasons, DataProviders are not asked if they can provide
for the given page at this point, so it is possible that `DataProvider` is scheduled for a page it cannot provide data for,
and that it will not be actually executed.

Actual running of DataProviders is done using `Runner` (`WikiRAG.Runner` service). It will receive a page and list of
DataProviders to be executed for the page. It will check if each DataProvider can provide data for the page
(using `canProvideForPage` method), and if so, it will call `provideForPage` method to get the data.
Finally, it will pass the data to the target (using `write` method).
Runner checks in page being indexed exists, and if not, adds `deleted` DataProvider to the list of DataProviders to be executed.
If DataProvider cannot provide data for the page, it will call `ITarget::remove` method to remove any previously
indexed data for the page for this DataProvider.

## Types of pages being indexed

Not all pages are suitable for indexing. This determination is done in `IndexabilityChecker`
(`WikiRAG._IndexabilityChecker` service and accessible over `Scheduler::canPageBeScheduled` method).
By default, following pages are indexed:
- Non-talk content pages
- File pages where file types are `OFFICE` or `TEXT` (`docx`, `doc`, `odt`, `pdf`, `txt`, `md`, ...)
- Any page allowed by `WikiRAGCanBeIndexed` hook

## Prevent page index by MAGIC WORD

Use `__NO_RAG_EXPORT__` magic word on a page to prevent it from being indexed.

## Configuration and extendability

### Attributes

- `WikiRAGTargets` - List of possible targets for data. Every target must have a key, and value is an OF spec,
producing instance of `ITarget`.
- `WikiRAGContextProviders` - List of possible context data providers. Every context provider must have a key, and value is an OF spec
- `WikiRAGDataProviders` - List of possible data providers. Every data provider must have a key, and value is an OF spec,
producing instance of `IPageDataProvider`.
  - In addition to DPs listed here, two implicit DPs are always available. These are added to the pipeline automatically when needed.
    - `id` - Provides a file with human-readable page identifier
    - `deleted` - provides an empty file, service as a flag that the page was deleted
- `WikiRAGChangeObservers` - List of possible change observers. Every change observer must have a key, and value is an OF spec,
producing instance of `IChangeObserver`.

### Config variables

- `wgWikiRAGTarget` - target to be used, must be one of targets defined in `WikiRAGTargets`. Value is array with values:
  - `type` - key of the target to be used
  - `configuration` - array of configuration params to be set on target object once initialized. Specific configuration 
  depends on target type.
- `wgWikiRAGPipeline` - list of data providers to be considered for indexing. Every entry must be a key of a
data provider defined in `WikiRAGDataProviders`. Even if listed here, data provider might still not provide the data,
in case `canProvideForPage` returns `false`

Examples

```php
$GLOBALS['wgWikiRAGTarget'] = [
	'type' => 'local-directory',
	'configuration' => [
		'path' => '/path/to/writable/directory'
	]
];
$GLOBALS['wgWikiRAGPipeline'] = [ 'content.wikitext','content.html', 'repofile', 'meta.json', 'acl.json' ];
```

### Hook

See hook interfaces for info on parameters and return values.
- `WikiRAGCanBeIndexed` - allows to control which pages are indexable.
- `WikiRAGMetadata` - allows adding information to metadata array.
- `WikiRAGRunForPage` - allows replacing `RevisionRecord` being indexed

## Maintenance scripts

Show current queue: `php extensions/WikiRAG/maintenance/showQueued.php`

Process current queue: `php extensions/WikiRAG/maintenance/exportQueued.php`

Show export history (only last entry for each processed provider):
`php extensions/WikiRAG/maintenance/showExportHistory.php --page=Main_Page`

## Benchmarking

- Scheduling a page: ~3ms
- Scheduling all (with pipeline check on `canProvideForPage`), for 2000 pages: 9.7s, average per page: 4ms
- Exporting pipeline with 5 DPs (incl. expensive rendering DPs) for 2000 pages: 228s (3.8min), average per page: 111ms (8.99 pages per second)

# Getting data by pulling

Use following APIs to access the data from a "pull" mechanism:

- `GET /wikirag/v1/list` - list all queued files in format `ID => [ ...data, 'providers' => [ ...provider keys ] ]`
- `GET /wikirag/v1/fetch?id=ID&provider=providerKey` - fetch a specific file by page ID and provider key. 
`ID` is UrlEncoded value of ID from `list` call, and `providerKey` is one of the queued providers keys.
This will provide a file stream to download or exception on any issue.

Use this in combination with `null-target`, as otherwise the target configured may remove items from the queue.

Note: Configure `$wgWikiRAGApiAllowedIP` to allow certain IP range to access the API.
