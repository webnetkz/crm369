package com.appswebnetkz.crm369

import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.ListView
import android.widget.ProgressBar
import android.widget.TextView
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.appbar.MaterialToolbar
import com.google.android.material.button.MaterialButton

class CollectionActivity : AppCompatActivity() {
    private lateinit var module: NativeModule
    private lateinit var progress: ProgressBar
    private lateinit var state: TextView
    private lateinit var list: ListView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        module = NativeModule.fromKey(intent.getStringExtra(EXTRA_MODULE)) ?: run {
            finish()
            return
        }
        setContentView(R.layout.activity_collection)
        progress = findViewById(R.id.collectionProgress)
        state = findViewById(R.id.collectionState)
        list = findViewById(R.id.collectionList)

        findViewById<MaterialToolbar>(R.id.collectionToolbar).apply {
            setTitle(module.titleResource)
            setNavigationOnClickListener { finish() }
        }
        findViewById<MaterialButton>(R.id.collectionRefresh).setOnClickListener { load() }
        list.setOnItemClickListener { parent, _, position, _ ->
            openItem(parent.adapter.getItem(position) as NativeListItem)
        }
        load()
    }

    private fun load() {
        progress.visibility = View.VISIBLE
        state.visibility = View.GONE
        NativeApiClient(this).get(module.endpoint) { result ->
            progress.visibility = View.GONE
            result.onSuccess { response ->
                val items = NativeListParser.parse(response, module)
                list.adapter = NativeListAdapter(this, items)
                state.apply {
                    text = getString(R.string.empty_list)
                    visibility = if (items.isEmpty()) View.VISIBLE else View.GONE
                }
            }.onFailure(::handleFailure)
        }
    }

    private fun openItem(item: NativeListItem) {
        if (module == NativeModule.Chats) {
            startActivity(ChatActivity.newIntent(this, item.id, item.title))
            return
        }
        if (module == NativeModule.Notifications && !item.raw.optBoolean("is_read")) {
            NativeApiClient(this).patch("/api/mobile/v1/notifications/${item.id}/read") { load() }
        }

        AlertDialog.Builder(this)
            .setTitle(item.title)
            .setMessage(item.subtitle.ifBlank { item.raw.toString(2) })
            .setPositiveButton(android.R.string.ok, null)
            .show()
    }

    private fun handleFailure(error: Throwable) {
        if (error is ApiException && error.statusCode == 401) {
            SecureSessionStore(this).clear()
            AppNavigator.openLogin(this, sessionExpired = true)
            return
        }
        state.text = error.message ?: getString(R.string.network_error)
        state.visibility = View.VISIBLE
    }

    companion object {
        private const val EXTRA_MODULE = "module"

        fun newIntent(context: Context, module: NativeModule): Intent =
            Intent(context, CollectionActivity::class.java).putExtra(EXTRA_MODULE, module.key)
    }
}
