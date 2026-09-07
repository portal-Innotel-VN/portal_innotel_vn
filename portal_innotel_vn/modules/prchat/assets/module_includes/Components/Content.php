<div class="content">
     <div id="sharedFiles">
          <i class="fa fa-times-circle-o" aria-hidden="true"></i>
          <div class="history_slider">
               <!-- Message and files history -->
          </div>
     </div>
     <div class="chat_group_options">
          <!-- Group options  -->
     </div>
     <div class="contact-profile">
          <svg onclick="chatBackMobile()" data-toggle="tooltip" title="Back" class="chat_back_mobile" viewBox="0 0 24 24">
               <path d="M20,11V13H8L13.5,18.5L12.08,19.92L4.16,12L12.08,4.08L13.5,5.5L8,11H20Z" />
          </svg>
          <img src="" class="img img-responsive staff-profile-image-small pull-left" alt="" />
          <p></p>
          <i class="fa fa-volume-up user_sound_icon" data-toggle="tooltip" title="<?= _l('chat_sound_notifications'); ?>"></i>
          <div class="social-media mright15">
               <svg data-toggle="tooltip" data-container="body" title="<?php echo _l('chat_shared_files'); ?>" data-placement="left" class="fa fa-share-alt" id="shared_user_files" viewBox="0 0 24 24">
                    <path d="M13.5,8H12V13L16.28,15.54L17,14.33L13.5,12.25V8M13,3A9,9 0 0,0 4,12H1L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3" />
               </svg>
               <a href="" id="fa-skype" data-toggle="tooltip" data-container="body" class="mright5" title="<?php echo _l('chat_call_on_skype'); ?>"><i class="fa fa-skype" aria-hidden="true"></i></a>
               <a href="" id="fa-facebook" target="_blank" class="mright5"><i class="fa fa-facebook" aria-hidden="true"></i></a>
               <a href="" id="fa-linkedin" target="_blank" class="mright5"><i class="fa fa-linkedin" aria-hidden="true"></i></a>
          </div>
     </div>
     <div class="messages" onscroll="loadMessages(this)">
          <svg class="message_loader" viewBox="0 0 50 50">
               <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
          </svg>
          <span class="userIsTyping bounce" id="">
               <img src="<?php echo module_dir_url('prchat', 'assets/chat_implements/userIsTyping.gif'); ?>" />
          </span>
          <ul>
          </ul>
     </div>
     <div class="group_messages" onscroll="loadGroupMessages(this)">
          <svg class="message_group_loader" viewBox="0 0 50 50">
               <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
          </svg>
          <div class="chat_group_messages">
               <ul>
               </ul>
          </div>
     </div>
     <?php if (isClientsEnabled()) : ?>
          <div class="client_messages" id="">
               <svg class="message_loader" viewBox="0 0 50 50">
                    <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
               </svg>
               <span class="userIsTyping bounce" id="">
                    <img src="<?php echo module_dir_url('prchat', 'assets/chat_implements/userIsTyping.gif'); ?>" />
               </span>
               <div class="chat_client_messages">
                    <!-- Client messages -->
                    <ul>
                    </ul>
               </div>
          </div>
     <?php endif; ?>
     <!-- Staff -->
     <?php loadChatComponent('StaffForm');  ?>
     <!-- Groups -->
     <?php loadChatComponent('GroupsForm');  ?>
     <!-- Clients -->
     <?php loadChatComponent('ClientsForm');  ?>
</div>