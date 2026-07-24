package com.appswebnetkz.crm369

import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.view.View
import android.view.inputmethod.EditorInfo
import android.widget.ListView
import android.widget.ProgressBar
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.appbar.MaterialToolbar
import com.google.android.material.button.MaterialButton
import com.google.android.material.textfield.TextInputEditText
import org.json.JSONObject

class ChatActivity : AppCompatActivity() {
    private lateinit var conversationId: String
    private lateinit var toolbar: MaterialToolbar
    private lateinit var progress: ProgressBar
    private lateinit var messagesList: ListView
    private lateinit var messageInput: TextInputEditText
    private lateinit var sendButton: MaterialButton

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        conversationId = intent.getStringExtra(EXTRA_CONVERSATION_ID).orEmpty()
        if (conversationId.isBlank()) {
            finish()
            return
        }

        setContentView(R.layout.activity_chat)
        toolbar = findViewById(R.id.chatToolbar)
        progress = findViewById(R.id.chatProgress)
        messagesList = findViewById(R.id.chatMessagesList)
        messageInput = findViewById(R.id.chatMessageInput)
        sendButton = findViewById(R.id.chatSendButton)

        toolbar.title = intent.getStringExtra(EXTRA_TITLE) ?: getString(R.string.module_chats)
        toolbar.setNavigationOnClickListener { finish() }
        sendButton.setOnClickListener { sendMessage() }
        messageInput.setOnEditorActionListener { _, actionId, _ ->
            if (actionId == EditorInfo.IME_ACTION_SEND) {
                sendMessage()
                true
            } else {
                false
            }
        }
        loadMessages()
    }

    private fun loadMessages() {
        progress.visibility = View.VISIBLE
        NativeApiClient(this).get("/api/mobile/v1/chats?conversation=$conversationId") { result ->
            progress.visibility = View.GONE
            result.onSuccess { response ->
                val activeConversation = response.optJSONObject("activeConversation") ?: JSONObject()
                activeConversation.optString("title").takeIf(String::isNotBlank)?.let { toolbar.title = it }
                val messages = activeConversation.optJSONArray("messages")
                val items = buildList {
                    for (index in 0 until (messages?.length() ?: 0)) {
                        val message = messages?.optJSONObject(index) ?: continue
                        val user = message.optJSONObject("user")
                        val sender = if (message.optBoolean("isOwn")) {
                            getString(R.string.you)
                        } else {
                            user?.optString("name").orEmpty().ifBlank { getString(R.string.module_chats) }
                        }
                        val timestamp = message.optString("createdAt")
                        add(
                            NativeListItem(
                                message.opt("id")?.toString().orEmpty(),
                                sender,
                                listOf(message.optString("body"), timestamp)
                                    .filter(String::isNotBlank)
                                    .joinToString("\n"),
                                message,
                            ),
                        )
                    }
                }
                messagesList.adapter = NativeListAdapter(this, items)
                if (items.isNotEmpty()) {
                    messagesList.setSelection(items.lastIndex)
                }
            }.onFailure(::handleFailure)
        }
    }

    private fun sendMessage() {
        val body = messageInput.text?.toString()?.trim().orEmpty()
        if (body.isBlank()) {
            return
        }

        sendButton.isEnabled = false
        NativeApiClient(this).post(
            "/api/mobile/v1/chats/$conversationId/messages",
            JSONObject().put("body", body),
        ) { result ->
            sendButton.isEnabled = true
            result.onSuccess {
                messageInput.text?.clear()
                loadMessages()
            }.onFailure(::handleFailure)
        }
    }

    private fun handleFailure(error: Throwable) {
        if (error is ApiException && error.statusCode == 401) {
            SecureSessionStore(this).clear()
            AppNavigator.openLogin(this, sessionExpired = true)
            return
        }
        messageInput.error = error.message ?: getString(R.string.network_error)
    }

    companion object {
        private const val EXTRA_CONVERSATION_ID = "conversation_id"
        private const val EXTRA_TITLE = "title"

        fun newIntent(context: Context, conversationId: String, title: String? = null): Intent =
            Intent(context, ChatActivity::class.java)
                .putExtra(EXTRA_CONVERSATION_ID, conversationId)
                .putExtra(EXTRA_TITLE, title)
    }
}
