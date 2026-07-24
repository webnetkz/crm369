package com.appswebnetkz.crm369

import android.content.Context
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ArrayAdapter
import android.widget.TextView

class NativeListAdapter(context: Context, items: List<NativeListItem>) :
    ArrayAdapter<NativeListItem>(context, R.layout.item_native_list, items) {

    override fun getView(position: Int, convertView: View?, parent: ViewGroup): View {
        val view = convertView ?: LayoutInflater.from(context).inflate(R.layout.item_native_list, parent, false)
        val item = getItem(position) ?: return view
        view.findViewById<TextView>(R.id.itemTitle).text = item.title
        view.findViewById<TextView>(R.id.itemSubtitle).apply {
            text = item.subtitle
            visibility = if (item.subtitle.isBlank()) View.GONE else View.VISIBLE
        }
        return view
    }
}
