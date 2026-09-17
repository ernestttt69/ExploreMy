# ExploreMY — Reviewed Use Cases and Functional Requirements

Reviewed against the local implementation on 16 September 2026.

This document describes the implemented behaviour of the eight submitted use cases. It preserves FR001–FR069. Quoted application messages below are the English versions; customer-facing translations follow the selected language. Native browser validation and confirmation button wording vary by browser and browser language. This is a code review, not verification of the currently deployed website.

## UC_001 — Continue with Google

**Objective:** Allow a user to register or sign in using a Google account.

**Actor:** User.

**Preconditions:** The user is not signed in, has a Google account, and opens the Login page.

### Basic flow

| Step | Actor / System action |
|---|---|
| 1 | The system displays the Google “Continue with Google” sign-in control. [FR001] |
| 2 | The user selects the control. |
| 3 | Google presents its account authentication interface. [FR002] |
| 4 | The user completes Google authentication. |
| 5 | The system receives and verifies the Google ID token. [FR003] |
| 6 | The system searches for an account using the verified Google account ID. [FR004] |
| 7 | If no account exists, the system creates one using the verified name, email, Google account ID and profile picture when available, and marks initial setup as required. [FR005] |
| 8 | The system signs the user in, regenerates the session identifier and records login activity. [FR007] |
| 9 | The system directs a user with pending initial setup to Setup; otherwise, it displays the Dashboard. [FR008] |

### Alternate flows

- **A1 — Existing account, Step 6:** The system reuses the account and synchronises its email with the verified Google information. It preserves an existing usable profile picture; a supplied Google picture may fill an absent or missing local picture. Continue at Step 8 and apply the same setup-status check at Step 9. [FR006, FR008]
- **A2 — Invalid token, Step 5:** The system returns M1 and rejects the token. [FR009]
- **A3 — Login processing exception:** The system returns M2 instead of a success response. [FR010]
- **A4 — Request or response handling failure:** The Login page displays M3 if its request cannot be processed by the browser. [FR010]

### Messages

- **M1:** “Invalid Google Token”
- **M2:** “Google login failed. Please try again.”
- **M3:** “Login failed”

### Constraints

1. A Google token must be verified before it is used to authenticate an account.
2. Account matching uses the Google ID, not the displayed name.
3. New users must complete initial setup before the Dashboard is shown. An existing account with setup still pending follows the same route.

### Postconditions

- **Success:** The account is authenticated, login activity is recorded, and Setup or Dashboard is displayed according to setup status.
- **Failure:** A failure message is returned or displayed; successful navigation is not completed. Do not claim that every processing exception rolls back all account/session changes: the login handler is not an atomic transaction.

### Functional requirements

| ID | Requirement |
|---|---|
| FR001 | The system shall display a Google “Continue with Google” control on the Login page. |
| FR002 | The system shall invoke the Google sign-in interface when the user selects the control. |
| FR003 | The system shall receive and verify the Google ID token. |
| FR004 | The system shall identify an existing account using the verified Google account ID. |
| FR005 | The system shall create an account from verified Google information when no matching account exists and mark its initial setup as required. |
| FR006 | The system shall reuse an existing account and synchronise its email address with the verified Google account information. |
| FR007 | The system shall authenticate the user, regenerate the session identifier and record login activity. |
| FR008 | The system shall direct users with pending initial setup to Setup and other authenticated users to the Dashboard. |
| FR009 | The system shall reject an invalid Google ID token and return “Invalid Google Token”. |
| FR010 | The system shall report Google login processing failures, and the Login page shall display a fallback message when its request cannot be processed. |

## UC_002 — View and Update Profile

**Objective:** Allow the user to view account information, update their photo, language and place preferences, and request account deletion.

**Actor:** Authenticated user.

**Precondition:** The user is signed in.

### Basic flow

| Step | Actor / System action |
|---|---|
| 1 | The user opens Profile. |
| 2 | The system displays the user's read-only name and email, and profile picture or fallback avatar. [FR011] |
| 3 | The system displays the selected interface language and place preference categories. [FR012] |
| 4 | The system displays “Google account connected” and a collapsible “Recent sign-ins” section containing up to five login records. [FR013] |
| 5 | The user views their settings and may expand the recent sign-ins section. |

### Alternate flows

- **A1 — Change photo:** The user selects “Change photo” and chooses an image. The system previews it. The user selects “Save changes”; the system validates and saves the submitted settings and displays M1. [FR014, FR017]
- **A2 — Change place preferences:** The user selects or deselects categories and selects “Save changes”. The system validates and saves the selection, including an empty selection, and displays M1. [FR015, FR017]
- **A3 — Change language:** The user selects English, Bahasa Melayu or Chinese and selects “Save changes”. The system saves the language. If it differs from the previously saved language, the browser reloads Profile in the new language. [FR016]
- **A4 — Invalid settings:** Validation rejects an invalid photo, language or category selection before saving. The system displays the applicable validation information; M2 applies to an invalid photo and M3 to invalid categories. [FR017]
- **A5 — Photo upload failure:** The image cannot be stored. The system reports M4; the selected photo is not successfully saved. [FR017]
- **A6 — Delete account:** The user selects “Delete my account”. The system displays M5 and M6 with a confirmation field. The user types “DELETE” and selects “Permanently delete”. The system validates the confirmation, deletes the account, signs the user out, invalidates the session and redirects to Login. [FR018–FR020]
- **A7 — Incorrect deletion confirmation:** Native browser validation prevents an empty or nonmatching value from being submitted normally. If an invalid request reaches the server, the server rejects it and does not delete the account. The browser prompt is not a fixed application message. [FR021]
- **A8 — Cancel deletion:** The user selects “Cancel” or the dialog close control. The system closes the dialog without deleting the account. [FR022]
- **A9 — Return:** The user selects “Back to dashboard”; the system opens the Dashboard. [FR023]

### Messages

- **M1 — Save-success notification:** “Account changes saved successfully.”
- **M2 — Invalid photo:** “Choose a valid image no larger than 2 MB.”
- **M3 — Invalid categories:** “Please select valid place preferences.”
- **M4 — Photo storage failure:** “Your photo could not be uploaded. Please try again later.”
- **M5 — Delete dialog title:** “Delete your account?”
- **M6 — Delete dialog instruction:** “This action cannot be undone. Enter DELETE to confirm.”
- **Additional save status:** “Saved. Your profile settings are up to date.”
- **Additional failed-save status:** “Could not save. Your changes have not been applied. Please try again.”
- **Connection error:** “Unable to connect. Check your connection and try again.”
- **Server confirmation-validation examples:** “The confirmation field is required.” or “The selected confirmation is invalid.” These are server responses, not guaranteed messages inside the deletion dialog.

The deletion controller stores “Your account and associated data have been permanently deleted.” in a success flash, but the current standalone Login template does not render that flash. Therefore, this UC does not promise that the user sees a deletion-success message after redirection.

### Constraints

1. Name and email are read-only; no phone number, birthday, completion percentage or personalisation toggle is provided by the current Profile form.
2. The uploaded profile picture must pass image validation and be no larger than 2 MB.
3. Photo, preferences and language share one “Save changes” submission. A preview is not a saved photo.
4. Language must be English, Bahasa Melayu or Chinese. Preference IDs must refer to existing categories and must not be duplicated.
5. Deletion requires the exact case-sensitive text “DELETE”.

### Postconditions

- **Success:** Profile information is displayed; successfully submitted settings are saved. Successful account deletion ends the session and redirects to Login.
- **Validation failure / cancellation:** Invalid settings are rejected. Incorrect or cancelled deletion does not delete the account.
- **Operational failure:** An error is reported. The profile save implementation does not provide a general transactional rollback guarantee for every possible storage/database failure.

### Functional requirements

| ID | Requirement |
|---|---|
| FR011 | The system shall display the authenticated user's read-only name, email and profile image or fallback avatar. |
| FR012 | The system shall display the user's selected language and place preference categories. |
| FR013 | The system shall indicate the connected Google account and allow the user to expand up to five recent login records. |
| FR014 | The system shall allow the user to preview a selected profile picture and save a valid picture when “Save changes” is submitted. |
| FR015 | The system shall save valid selected or deselected place categories when “Save changes” is submitted, including an empty selection. |
| FR016 | The system shall save English, Bahasa Melayu or Chinese as the user's language and reload Profile when the saved language changes. |
| FR017 | The system shall validate submitted profile settings and report invalid settings or unsuccessful photo uploads. |
| FR018 | The system shall display an account deletion dialog requiring the exact confirmation text “DELETE”. |
| FR019 | The system shall delete the authenticated user's account after validating the deletion confirmation. |
| FR020 | The system shall sign the user out, invalidate the session and redirect to Login following successful account deletion. |
| FR021 | The system shall reject an incorrect deletion confirmation without deleting the account. |
| FR022 | The system shall allow the user to cancel account deletion. |
| FR023 | The system shall provide navigation from Profile to the Dashboard. |

## UC_003 — View and Remove Saved Places

**Objective:** Allow the user to view and remove their saved attractions.

**Actor:** Authenticated user.

**Precondition:** The user is signed in.

### Basic flow

1. The user opens Saved Places.
2. The system retrieves saved attractions belonging to that user. [FR024]
3. The system displays their names and available images. [FR025]
4. The user views the saved places.

### Alternate flows

- **A1 — Remove, Step 4:** The user selects “Remove”. The browser asks for confirmation using M2. After confirmation, the system removes the user's saved association, removes the corresponding card after a successful AJAX response, and displays M3. [FR026, FR027]
- **A2 — Cancel removal:** The user cancels the confirmation. No removal request is submitted. [FR026]
- **A3 — Empty list, Step 2:** The system displays M1 when rendering a page with no saved places. [FR028]
- **A4 — Failed request:** The interface displays the returned error or a generic request error. A network error does not prove that the server made no change; reload to establish the saved state.

### Messages

- **M1:** “No saved places yet”
- **M2:** “Remove this attraction from your wishlist?”
- **M3:** “Attraction removed from your wishlist.”

### Constraints

1. Users access and remove only their own saved associations.
2. Removing a save does not delete the attraction catalogue record.
3. Collection management and itinerary generation are separate workflows and are not prerequisites for viewing or removing saved places.

### Postconditions

- **Success:** Saved places are displayed; a successfully removed association no longer belongs to the user.
- **Cancellation:** The saved association remains unchanged.
- **Failure:** Successful removal is not confirmed. The client retains its card when the AJAX request fails.

### Functional requirements

| ID | Requirement |
|---|---|
| FR024 | The system shall retrieve the authenticated user's saved attractions. |
| FR025 | The system shall display the saved attractions with names and available images. |
| FR026 | The system shall request confirmation before removing a saved association and allow the user to cancel. |
| FR027 | The system shall remove the corresponding displayed card and show the removal notification after a successful AJAX removal. |
| FR028 | The system shall display “No saved places yet” when rendering an empty saved-places page. |

## UC_004 — Logout

**Objective:** End the user's authenticated session.

**Actor:** Authenticated user.

**Precondition:** The user is signed in.

### Basic flow

1. The user selects “Logout”.
2. The system signs the user out and invalidates the session. [FR029]
3. Session invalidation clears the session-based chatbot history. [FR030]
4. The system redirects to Explore. [FR031]

**Alternate flows:** No dedicated application-level logout-failure flow is implemented.

**Messages:** No dedicated logout-success message.

**Constraints:** Logout does not delete database-persisted account information, preferences, saved places or trips.

**Postconditions:** The authenticated session has ended and Explore is displayed. Do not assert that every failure leaves the user signed in; a redirect/network failure can occur after logout has already completed.

### Functional requirements

| ID | Requirement |
|---|---|
| FR029 | The system shall sign the authenticated user out and invalidate the current session on logout. |
| FR030 | The system shall clear session-based chatbot history as part of session invalidation. |
| FR031 | The system shall redirect the user to Explore after logout. |

## UC_005 — Chat with AI

**Objective:** Provide AI assistance about Malaysia travel and use of ExploreMY.

**Actor:** Authenticated user.

**Precondition:** The user is signed in and has access to the Ask AI control.

### Basic flow

1. The user selects “Ask AI”.
2. The system opens the chat interface containing a welcome message, input, FAQ choices and any restored session messages. [FR032]
3. The user enters a question and selects “Send”.
4. The system validates the submitted conversation. [FR033]
5. The system supplies recent conversation, relevant attraction records when available, product instructions and limited current-user context to the AI service. [FR034]
6. The system receives and cleans the response, including conversion of supported Markdown table output into numbered text. It requests concise formatting and the language detected from the latest question. [FR035, FR036]
7. The system displays the response and retains the most recent ten messages in the session following a successful response. [FR035, FR041]

### Alternate flows

- **A1 — FAQ:** The user selects an FAQ question. It populates and submits the same chat form, including validation, then continues at Step 4. It is not a separate fixed-answer endpoint. [FR037]
- **A2 — Empty input:** The browser prevents an empty required input from submitting; whitespace-only text is ignored by the chat script. [FR033]
- **A3 — Excessive length:** The normal input is limited to 1,500 characters. A bypassed overlength request is rejected by backend validation. The current chat client displays the general M1 for an unsuccessful response rather than exposing its field-validation details. [FR038]
- **A4 — Provider/network failure:** The system displays M1 when no successful response is obtained. [FR039]
- **A5 — Expired or unverifiable session:** An HTTP 401 or 419 response causes the chat interface to display M2. [FR040]
- **A6 — Follow-up:** The user submits another question; the most recent conversation messages are included in the same process. [FR034, FR041]
- **A7 — Close:** The user closes the chat panel. Closing it does not itself erase the session history. [FR032]

### Messages

- **M1:** “Currently busy. Please try again later.”
- **M2:** “Your session has expired or could not be verified. Refresh the page and sign in again if needed.”
- **Backend-only invalid-final-role response:** “Please send a valid message.” The current client does not display this specific response; it uses M1.

### Constraints

1. The message endpoint requires authentication and is throttled to 15 requests per minute.
2. The submitted conversation contains 1–10 messages, each with an allowed role and nonempty text of at most 1,500 characters. Its final message must be from the user.
3. The session stores at most ten messages, counting user and assistant messages, not ten conversation pairs. Previously rendered bubbles may remain visible until the page is reloaded.
4. Logout clears session history; closing the panel does not.
5. The system instructs the model to use short paragraphs or numbered lists and avoid tables. Specific attraction facts must be grounded in supplied records, and unavailable information must not be invented.
6. The interface displays an AI accuracy notice. Model instructions do not guarantee that every factual response or language choice is correct.

### Postconditions

- **Success:** A reply is displayed and the recent successful conversation is saved in the session.
- **Failure:** No successful new AI reply is shown; an applicable error replaces the loading indicator. The submitted user bubble and older conversation may remain visible.

### Functional requirements

| ID | Requirement |
|---|---|
| FR032 | The system shall allow the user to open and close a chat panel with a welcome message, input, FAQ choices and restored session history when available. |
| FR033 | The system shall validate the submitted conversation before contacting the AI service. |
| FR034 | The system shall provide recent conversation, relevant attraction information, product instructions and limited current-user context to the AI service. |
| FR035 | The system shall receive, clean and display successful AI responses. |
| FR036 | The system shall request short paragraphs or numbered lists without tables and a response in the detected language of the latest user question. |
| FR037 | The system shall submit FAQ selections through the same validated chat flow as typed questions. |
| FR038 | The system shall restrict normal message entry to 1,500 characters and reject overlength submissions at the backend. |
| FR039 | The chat interface shall display “Currently busy. Please try again later.” for unsuccessful requests other than recognised session-expiry responses. |
| FR040 | The chat interface shall display the refresh/sign-in instruction when the endpoint returns HTTP 401 or 419. |
| FR041 | The system shall store the most recent ten conversation messages in the login session after a successful AI response. |

## UC_006 — Add Attraction

**Objective:** Allow an administrator to create an attraction with optional photos.

**Actor:** Admin.

**Preconditions:** The administrator has authenticated with the configured admin access code and opens Attraction management.

### Basic flow

1. The admin selects “Add attraction”; the system opens the creation form. [FR042]
2. The admin enters Place ID, attraction name, state, location, entrance fee and budget level.
3. The admin may enter description, operating hours, nearby transport and rating.
4. The admin may select images. The system previews selected images. [FR043]
5. The admin selects “Create attraction”.
6. The system validates the submitted fields and selected images. [FR044]
7. The system creates the attraction and any uploaded image records within a database transaction. [FR045]
8. The system displays M1 and returns to Attraction management. [FR045]

### Alternate flows

- **A1 — Duplicate Place ID, Step 6:** Reject the submission and display M2. [FR046]
- **A2 — Missing required information, Step 6:** Reject the submission and display applicable field validation. Native browser checks may prevent submission first. [FR047]
- **A3 — Invalid rating, Step 6:** Reject a nonnumeric rating or a value outside 0–5. [FR048]
- **A4 — Invalid images, Step 6:** Reject invalid image content, images over 3 MB, or more than ten selected files. [FR049]
- **A5 — Upload failure, Step 7:** Roll back the attraction database transaction. A handled cloud-upload error returns M3. A local filesystem or unexpected exception uses the general error path and may not display M3. [FR050]
- **A6 — Cancel:** The admin selects “Cancel” or “Back to list”. The browser returns to Attraction management without submitting pending data. [FR042]

### Messages

- **M1:** “Attraction added successfully.”
- **M2:** “The place id has already been taken.”
- **M3 — Handled cloud-upload failure:** “Photo upload failed. Please try again later. Your place changes were not saved.”
- **Required-field example:** “The place id field is required.”
- **Rating-range example:** “The rating must be between 0 and 5.”
- **Too many images:** “The images must not have more than 10 items.”
- **Individual-image errors:** Framework-generated field/index messages identify the invalid image or the 3072-kilobyte limit. Do not replace these with invented fixed UI sentences.

### Constraints

1. Only authenticated admins may submit.
2. Place ID is the unique, user-entered `place_id`, not the database-generated `attraction_id`.
3. Required fields are Place ID, attraction name, state, location, entrance fee and budget level. State must exist.
4. Description, operating hours, nearby transport, rating and images are optional.
5. If supplied, rating must be numeric and between 0 and 5.
6. Maximum ten images per submission, each at most 3 MB. The picker advertises JPG, PNG and WebP; backend validation uses the framework image rule rather than an explicit three-format MIME allowlist.
7. Validation checks data format and constraints; it does not verify real-world attraction legitimacy, safety or information freshness.

### Postconditions

- **Success:** The attraction and associated image records are saved and the admin returns to the list.
- **Validation/upload failure:** No new attraction record is committed. Files already transferred before a later upload failure are not guaranteed to be removed from external storage.
- **Cancellation:** No pending form data is submitted.

### Functional requirements

| ID | Requirement |
|---|---|
| FR042 | The system shall display the Add Attraction form and provide cancellation/navigation back to the management list. |
| FR043 | The system shall preview selected attraction photos before submission. |
| FR044 | The system shall validate attraction fields and selected images before creation. |
| FR045 | The system shall save the attraction and associated image records on success, display “Attraction added successfully.” and return to the management list. |
| FR046 | The system shall reject an already-used Place ID. |
| FR047 | The system shall require Place ID, attraction name, a valid state, location, entrance fee and budget level. |
| FR048 | The system shall accept an optional rating only when it is numeric and within 0–5. |
| FR049 | The system shall validate image content and allow at most ten files per submission, each no larger than 3 MB. |
| FR050 | The system shall roll back attraction creation in the database if image storage throws an exception and report handled cloud-upload failures with the configured photo-upload message. |

## UC_007 — Delete Attraction

**Objective:** Allow an admin to delete an existing attraction.

**Actor:** Admin.

**Preconditions:** An authenticated admin is viewing Attraction management and the selected attraction exists.

### Basic flow

1. The admin selects “Delete” for an attraction.
2. The system opens a dialog with M2 and M3 identifying that attraction. [FR051]
3. The admin selects “Delete permanently”.
4. The system deletes the attraction record and attempts cleanup of associated local image files. [FR052]
5. Following a successful response, the system removes the row from the displayed list, closes the dialog and displays M1. [FR053]

### Alternate flows

- **A1 — Cancel, Step 3:** The admin selects “Cancel” or closes the dialog. No deletion is submitted. [FR054]
- **A2 — Unsuccessful request:** The AJAX layer displays an available server error or generic request error. The interface does not run its successful-deletion update. This is not a guarantee that every database/file operation has been rolled back. [FR055]

### Messages

- **M1:** “Attraction deleted successfully.”
- **M2 — Dialog title:** “Delete attraction?”
- **M3 — Dialog warning:** “This will permanently delete :name and remove it from every user's saved places.” (`:name` is replaced by the selected name.)
- **Error fallback:** “Request failed (:status).” when no server message is available; a network exception may supply browser-specific text. There is no dedicated “Attraction could not be deleted” application message.

### Constraints

1. Admin authentication is required.
2. The normal interface requests confirmation before submitting deletion.
3. Deletion is not a soft-hide action. The implementation does not guarantee atomic rollback of attraction deletion and subsequent local file cleanup.

### Postconditions

- **Success:** The attraction is deleted and its displayed row is removed.
- **Cancellation:** The attraction remains unchanged.
- **Request failure:** Successful completion is not confirmed; the client does not remove the row through its success handler. Do not claim that the underlying record necessarily remains unchanged.

### Functional requirements

| ID | Requirement |
|---|---|
| FR051 | The system shall display a confirmation dialog with the attraction name and warning about its removal from user saves. |
| FR052 | The system shall process attraction deletion when an authenticated admin submits the confirmed action. |
| FR053 | The system shall remove the displayed row, close the dialog and display “Attraction deleted successfully.” following a successful AJAX response. |
| FR054 | The system shall allow the admin to cancel without submitting deletion. |
| FR055 | The interface shall report unsuccessful deletion requests using an available server error or generic request error and shall not perform its successful-deletion UI update. |

## UC_008 — Update Attraction

**Objective:** Allow an admin to edit attraction details, add photos and remove existing photo associations.

**Actor:** Admin.

**Preconditions:** The admin is authenticated and the selected attraction exists.

### Basic flow

1. The admin selects “Edit”.
2. The system displays the existing attraction fields and gallery. [FR056]
3. The admin changes the desired details and optionally selects additional images.
4. The system previews any newly selected images. [FR057]
5. The admin selects “Save changes”.
6. The system validates the updated fields and newly selected images. [FR058]
7. The system updates the attraction and adds uploaded image records within a database transaction. [FR059]
8. The system displays M1 and returns to Attraction management. [FR059]

### Alternate flows

- **A1 — Duplicate Place ID, Step 6:** Reject a Place ID used by another attraction and display M2. The attraction may retain its own current Place ID. [FR060]
- **A2 — Missing required fields, Step 6:** Reject invalid/missing required values and report the relevant validation errors. [FR061]
- **A3 — Invalid rating, Step 6:** Reject nonnumeric values or values outside 0–5. [FR062]
- **A4 — Invalid new images, Step 6:** Reject invalid images, files over 3 MB or more than ten new images. [FR063]
- **A5 — Upload failure, Step 7:** Roll back the pending database update if image storage throws an exception. For handled cloud-upload failures, return M3. Other exceptions use the general error path. [FR064]
- **A6 — Delete existing image, Step 2:** The admin selects “Delete image”. The browser displays M4. When confirmed, the system submits a separate deletion request, removes the image association, attempts local-file deletion where applicable, returns to the edit page and displays M5. It does not wait for “Save changes”. [FR065, FR066]
- **A7 — Cancel image deletion:** The admin cancels the browser confirmation. The image remains associated with the attraction. [FR067]
- **A8 — Cancel editing:** The admin selects “Cancel” or “Back to list”. The browser leaves without submitting pending details or newly selected files. Previously confirmed image deletions remain effective. [FR068, FR069]

### Messages

- **M1:** “Attraction updated successfully.”
- **M2:** “The place id has already been taken.”
- **M3 — Handled cloud-upload failure:** “Photo upload failed. Please try again later. Your place changes were not saved.”
- **M4:** “Delete this image?”
- **M5:** “Image deleted successfully.”
- Required-field, rating and image validation use the same framework messages described in UC_006.

### Constraints

1. Only authenticated admins may update an attraction.
2. Required fields and limits are the same as UC_006; the Place ID must be unique excluding the current record.
3. New images are optional. The limit is ten new files per submission, each up to 3 MB, not ten total stored photos per attraction.
4. Confirmed image deletion is a separate immediate operation; later cancellation does not undo it.
5. The current image-delete submission reloads the edit page. Unsaved form changes may be lost during that navigation.
6. Removing an image association does not promise remote Cloudinary asset deletion; the controller explicitly deletes local image files only.

### Postconditions

- **Success:** Updated details and newly uploaded image records are saved, and the management page is displayed.
- **Validation/upload failure:** Pending database changes are not committed. Already-transferred files are not guaranteed to be cleaned up after a later failure.
- **Cancellation:** Pending form edits are not submitted. Independently completed image deletions remain effective.

### Functional requirements

| ID | Requirement |
|---|---|
| FR056 | The system shall display the selected attraction's existing details and image gallery for editing. |
| FR057 | The system shall preview newly selected images before update submission. |
| FR058 | The system shall validate updated fields and new images before saving. |
| FR059 | The system shall save valid updates and new image records, display “Attraction updated successfully.” and return to the management list. |
| FR060 | The system shall reject a Place ID used by another attraction while allowing the current record to retain its own ID. |
| FR061 | The system shall require Place ID, attraction name, a valid state, location, entrance fee and budget level. |
| FR062 | The system shall accept an optional rating only when numeric and within 0–5. |
| FR063 | The system shall validate new images and accept at most ten per submission, each no larger than 3 MB. |
| FR064 | The system shall roll back pending database updates if image storage throws an exception and report handled cloud-upload failures with the configured upload message. |
| FR065 | The system shall request confirmation before submitting deletion of an existing image. |
| FR066 | The system shall remove a confirmed image association in a separate request without requiring “Save changes”, and report successful deletion. |
| FR067 | The system shall allow cancellation of image deletion without submitting the removal. |
| FR068 | The system shall allow the admin to leave using “Cancel” or “Back to list” without submitting pending details or newly selected images. |
| FR069 | The system shall preserve the effect of previously completed image deletions when the admin later cancels editing. |

## Scope and implementation notes for the report author

1. These eight use cases are not the complete system specification. Initial Setup, guest language switching, collection management, admin access-code login/logout, and viewing/searching the admin attraction list need separate coverage if absent elsewhere in the report.
2. Setup requires a supported language and permits no selected categories. Saving setup leads to Dashboard. Existing users can change those settings through Profile.
3. The login client currently navigates to `/dashboard` on a successful response rather than using the returned redirect URL; the Dashboard handler then redirects pending-setup users to Setup. The user-visible outcome in UC_001 remains correct.
4. Detailed backend chat validation messages are not presented by the current chat client. A requirement to display an explicit overlength error would be a proposed improvement, not an implemented feature.
5. Generic request failure does not establish that a server-side write did not occur. Avoid “unchanged on every failure” guarantees for nontransactional actions or lost responses.
6. Browser-native prompts are not fixed English application messages. Do not invent a fixed sentence for them in the Message Section.
7. Account-deletion success is flashed by the controller but not rendered by the current Login page. Do not describe that text as a verified visible notification.
8. Source files checked: AuthController, SetupController, ProfileController, ChatbotController, AiChatService, SystemGuideContext, customer AttractionController, Admin/AttractionController, routes/web.php, the Login/Profile/Saved Places/Admin/Chatbot views, chatbot.js, ajax-crud.js, profile-save.js, and relevant English translation files.
