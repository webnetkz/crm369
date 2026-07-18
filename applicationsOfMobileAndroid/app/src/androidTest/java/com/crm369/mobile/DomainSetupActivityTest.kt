package com.appswebnetkz.crm369

import android.content.Context
import android.content.Intent
import androidx.test.core.app.ActivityScenario
import androidx.test.espresso.Espresso.onView
import androidx.test.espresso.assertion.ViewAssertions.doesNotExist
import androidx.test.espresso.assertion.ViewAssertions.matches
import androidx.test.espresso.matcher.ViewMatchers.isDisplayed
import androidx.test.espresso.matcher.ViewMatchers.withId
import androidx.test.espresso.matcher.ViewMatchers.withText
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
class DomainSetupActivityTest {
    private val context = InstrumentationRegistry.getInstrumentation().targetContext

    @Before
    fun clearPreferences() {
        context.getSharedPreferences(PREFERENCES_NAME, Context.MODE_PRIVATE)
            .edit()
            .clear()
            .commit()
    }

    @Test
    fun showsMinimalDomainSetupScreenWithCrm369Branding() {
        ActivityScenario.launch<DomainSetupActivity>(
            Intent(context, DomainSetupActivity::class.java)
                .putExtra(DomainSetupActivity.EXTRA_FORCE_EDIT, true),
        ).use {
            onView(withId(R.id.brandLogo)).check(matches(isDisplayed()))
            onView(withId(R.id.brandText)).check(matches(withText(R.string.domain_brand)))
            onView(withId(R.id.brandText)).check(matches(isDisplayed()))
            onView(withId(R.id.domainInputLayout)).check(matches(isDisplayed()))
            onView(withId(R.id.continueButton)).check(matches(isDisplayed()))
            onView(withText(R.string.domain_title)).check(doesNotExist())
            onView(withText(R.string.domain_description)).check(doesNotExist())
            onView(withText(R.string.domain_helper)).check(doesNotExist())
        }
    }

    companion object {
        private const val PREFERENCES_NAME = "crm369_mobile_preferences"
    }
}
