package com.ialovers.mobile

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.ialovers.mobile.ui.screens.AuthChoiceScreen
import com.ialovers.mobile.ui.screens.LoginScreen
import com.ialovers.mobile.ui.screens.MainScreen
import com.ialovers.mobile.ui.screens.RegisterScreen
import com.ialovers.mobile.ui.screens.SplashScreen
import com.ialovers.mobile.ui.theme.IaLoversTheme

class MainActivity : ComponentActivity() {
    private val viewModel by viewModels<AppViewModel> {
        AppViewModel.factory(applicationContext)
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        setContent {
            IaLoversTheme {
                Surface(modifier = Modifier.fillMaxSize()) {
                    MobileApp(viewModel = viewModel)
                }
            }
        }
    }
}

@Composable
private fun MobileApp(viewModel: AppViewModel) {
    when (viewModel.rootDestination) {
        RootDestination.Splash -> SplashScreen()
        RootDestination.AuthChoice -> AuthChoiceScreen(
            authMessage = viewModel.authMessage,
            onLogin = viewModel::goToLogin,
            onRegister = viewModel::goToRegister,
        )
        RootDestination.Login -> LoginScreen(
            isBusy = viewModel.isBusy,
            message = viewModel.authMessage,
            error = viewModel.authError,
            onBack = viewModel::goToAuthChoice,
            onLogin = viewModel::login,
            onGoRegister = viewModel::goToRegister,
        )
        RootDestination.Register -> RegisterScreen(
            isBusy = viewModel.isBusy,
            message = viewModel.authMessage,
            error = viewModel.authError,
            pendingRegistration = viewModel.pendingRegistration,
            onBack = {
                if (viewModel.pendingRegistration != null) {
                    viewModel.cancelPendingRegistration()
                } else {
                    viewModel.goToAuthChoice()
                }
            },
            onStartRegistration = viewModel::startRegistration,
            onVerifyCode = viewModel::verifyRegistration,
            onResendCode = viewModel::resendRegistrationCode,
            onCancelPending = viewModel::cancelPendingRegistration,
            onGoLogin = viewModel::goToLogin,
        )
        RootDestination.Main -> MainScreen(
            selectedTab = viewModel.selectedTab,
            activePostId = viewModel.activePostId,
            exploreState = viewModel.exploreState,
            followingState = viewModel.followingState,
            profileState = viewModel.profileState,
            postDetailState = viewModel.postDetailState,
            onSelectTab = viewModel::selectTab,
            onRefreshFeed = viewModel::refreshFeed,
            onLoadMoreFeed = viewModel::loadMoreFeed,
            onRefreshProfile = viewModel::refreshProfile,
            onOpenPost = viewModel::openPost,
            onClosePost = viewModel::closePost,
            onToggleLike = viewModel::toggleLike,
            onCreateComment = viewModel::createComment,
            onLogout = viewModel::logout,
        )
    }
}
