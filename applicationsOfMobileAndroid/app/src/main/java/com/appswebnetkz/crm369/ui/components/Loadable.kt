package com.appswebnetkz.crm369.ui.components

import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.lazy.LazyListState
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.Stable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshotFlow
import androidx.compose.ui.Modifier
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.filter
import kotlinx.coroutines.launch

/**
 * State holder for data loaded from the API.
 *
 * - [data] keeps the last successful result (it stays visible while refreshing),
 * - [error] is the last failure (null after a successful load),
 * - [isLoading] is true during the first load, [isRefreshing] during pull-to-refresh.
 */
@Stable
class Loadable<T> internal constructor(
    private val scope: CoroutineScope,
    private val loader: suspend () -> T,
) {
    var data: T? by mutableStateOf(null)
        private set
    var error: Throwable? by mutableStateOf(null)
        private set
    var isLoading: Boolean by mutableStateOf(false)
        private set
    var isRefreshing: Boolean by mutableStateOf(false)
        private set

    private var job: Job? = null
    private var generation = 0

    /** Loads (or reloads) the data, keeping the current data visible. */
    fun load() = start(refresh = false)

    /** Reloads the data showing the pull-to-refresh indicator. */
    fun refresh() = start(refresh = true)

    /** Replaces the data locally (e.g. with the entity returned by an update call). */
    fun set(value: T) {
        data = value
        error = null
    }

    /** Transforms the current data locally (optimistic updates). */
    fun update(transform: (T) -> T) {
        val current = data ?: return
        data = transform(current)
    }

    private fun start(refresh: Boolean) {
        job?.cancel()
        val current = ++generation
        if (refresh) {
            isRefreshing = true
        } else {
            isLoading = data == null
        }
        job = scope.launch {
            try {
                val result = loader()
                if (current == generation) {
                    data = result
                    error = null
                }
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Throwable) {
                if (current == generation) {
                    error = exception
                }
            } finally {
                if (current == generation) {
                    isLoading = false
                    isRefreshing = false
                }
            }
        }
    }
}

/**
 * Remembers a [Loadable] and starts loading it. The loader is restarted whenever one of [keys]
 * changes. Example:
 *
 * ```
 * val api = rememberApi()
 * val contact = rememberLoadable(contactId) { api.get("contacts/$contactId").dataObject() }
 * ```
 */
@Composable
fun <T> rememberLoadable(vararg keys: Any?, loader: suspend () -> T): Loadable<T> {
    val scope = rememberCoroutineScope()
    val currentLoader by rememberUpdatedState(loader)
    val loadable = remember(*keys) { Loadable(scope) { currentLoader() } }
    LaunchedEffect(loadable) { loadable.load() }
    return loadable
}

/**
 * Shows loading / error / empty / content states of a [Loadable] with pull-to-refresh.
 * The content must be scrollable (LazyColumn or a Column with verticalScroll) for the
 * pull gesture to work.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun <T> LoadableContent(
    loadable: Loadable<T>,
    modifier: Modifier = Modifier,
    isEmpty: (T) -> Boolean = { false },
    empty: @Composable BoxScope.() -> Unit = {},
    content: @Composable BoxScope.(T) -> Unit,
) {
    val data = loadable.data
    val error = loadable.error
    PullToRefreshBox(
        isRefreshing = loadable.isRefreshing,
        onRefresh = { loadable.refresh() },
        modifier = modifier.fillMaxSize(),
    ) {
        when {
            data != null && isEmpty(data) -> empty()
            data != null -> content(data)
            error != null -> ErrorState(error = error, onRetry = { loadable.load() })
            else -> LoadingState()
        }
    }
}

/** One page of a paginated list. */
data class PageResult<T>(
    val items: List<T>,
    val hasMore: Boolean,
    val total: Int? = null,
)

/**
 * State holder for infinite lists backed by Laravel paginators (`meta.current_page`,
 * `meta.last_page`). Call [loadMore] from [LoadMoreEffect].
 *
 * Pass [distinctBy] (the item id) whenever the list uses `items(..., key = { it.id })`: records
 * created between two page loads shift the pages, and a duplicated key crashes a LazyColumn.
 */
@Stable
class PagedLoadable<T> internal constructor(
    private val scope: CoroutineScope,
    private val distinctBy: ((T) -> Any?)?,
    private val loader: suspend (page: Int) -> PageResult<T>,
) {
    var items: List<T> by mutableStateOf(emptyList())
        private set
    var error: Throwable? by mutableStateOf(null)
        private set
    var isLoading: Boolean by mutableStateOf(false)
        private set
    var isRefreshing: Boolean by mutableStateOf(false)
        private set
    var isLoadingMore: Boolean by mutableStateOf(false)
        private set
    var hasMore: Boolean by mutableStateOf(false)
        private set
    var total: Int? by mutableStateOf(null)
        private set
    var isLoaded: Boolean by mutableStateOf(false)
        private set

    private var page = 0
    private var job: Job? = null
    private var generation = 0

    fun load() = reload(refresh = false)

    fun refresh() = reload(refresh = true)

    fun loadMore() {
        if (!hasMore || isLoadingMore || isLoading || isRefreshing) return
        val current = generation
        isLoadingMore = true
        job = scope.launch {
            try {
                val result = loader(page + 1)
                if (current == generation) {
                    page += 1
                    items = merge(items, result.items)
                    hasMore = result.hasMore && result.items.isNotEmpty()
                    total = result.total ?: total
                }
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Throwable) {
                if (current == generation) error = exception
            } finally {
                if (current == generation) isLoadingMore = false
            }
        }
    }

    /** Local list changes (remove a deleted item, replace an updated one). */
    fun update(transform: (List<T>) -> List<T>) {
        items = transform(items)
    }

    private fun merge(current: List<T>, next: List<T>): List<T> {
        val keyOf = distinctBy ?: return current + next
        return (current + next).distinctBy(keyOf)
    }

    private fun reload(refresh: Boolean) {
        job?.cancel()
        val current = ++generation
        if (refresh) isRefreshing = true else isLoading = !isLoaded
        isLoadingMore = false
        job = scope.launch {
            try {
                val result = loader(1)
                if (current == generation) {
                    page = 1
                    items = result.items
                    hasMore = result.hasMore && result.items.isNotEmpty()
                    total = result.total
                    error = null
                    isLoaded = true
                }
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Throwable) {
                if (current == generation) error = exception
            } finally {
                if (current == generation) {
                    isLoading = false
                    isRefreshing = false
                }
            }
        }
    }
}

/**
 * Remembers a [PagedLoadable] and loads the first page; restarts when one of [keys] changes.
 * Example: `rememberPagedLoadable(search, distinctBy = { item: Contact -> item.id }) { page -> ... }`.
 */
@Composable
fun <T> rememberPagedLoadable(
    vararg keys: Any?,
    distinctBy: ((T) -> Any?)? = null,
    loader: suspend (page: Int) -> PageResult<T>,
): PagedLoadable<T> {
    val scope = rememberCoroutineScope()
    val currentLoader by rememberUpdatedState(loader)
    val paged = remember(*keys) { PagedLoadable<T>(scope, distinctBy) { page -> currentLoader(page) } }
    LaunchedEffect(paged) { paged.load() }
    return paged
}

/** Loading / error / empty states of a [PagedLoadable] with pull-to-refresh. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun <T> PagedContent(
    paged: PagedLoadable<T>,
    modifier: Modifier = Modifier,
    empty: @Composable BoxScope.() -> Unit = {},
    content: @Composable BoxScope.(List<T>) -> Unit,
) {
    val error = paged.error
    PullToRefreshBox(
        isRefreshing = paged.isRefreshing,
        onRefresh = { paged.refresh() },
        modifier = modifier.fillMaxSize(),
    ) {
        when {
            paged.isLoaded && paged.items.isEmpty() -> empty()
            paged.isLoaded -> content(paged.items)
            error != null -> ErrorState(error = error, onRetry = { paged.load() })
            else -> LoadingState()
        }
    }
}

/** Calls [onLoadMore] when the user scrolls close to the end of a lazy list. */
@Composable
fun LoadMoreEffect(
    listState: LazyListState,
    enabled: Boolean,
    threshold: Int = 5,
    onLoadMore: () -> Unit,
) {
    val currentOnLoadMore by rememberUpdatedState(onLoadMore)
    val currentEnabled by rememberUpdatedState(enabled)
    LaunchedEffect(listState) {
        snapshotFlow {
            val layoutInfo = listState.layoutInfo
            val lastVisible = layoutInfo.visibleItemsInfo.lastOrNull()?.index ?: 0
            layoutInfo.totalItemsCount > 0 && lastVisible >= layoutInfo.totalItemsCount - threshold
        }
            .distinctUntilChanged()
            .filter { it }
            .collect { if (currentEnabled) currentOnLoadMore() }
    }
}
