# disqus-hashover-import
Code for importing Disqus comment in HashOver Next

## Usage

This code require, for avatars to modify HashOver library here is diff:

```diff
diff --git a/hashover/backend/classes/commentparser.php b/hashover/backend/classes/commentparser.php
index 15b5a2b..bf13c06 100644
--- a/hashover/backend/classes/commentparser.php
+++ b/hashover/backend/classes/commentparser.php
@@ -106,10 +106,16 @@ class CommentParser
 
                // Get avatar icons
                if ($this->setup->iconMode !== 'none') {
+
                        if ($this->setup->iconMode === 'image') {
                                // Get MD5 hash for Gravatar
                                $hash = Misc::getArrayItem ($comment, 'email_hash') ?: '';
-                               $output['avatar'] = $this->avatars->getGravatar ($hash);
+                               $avatar = Misc::getArrayItem ($comment, 'avatar');
+                               if (!empty($avatar)) {
+                                       $output['avatar'] = $avatar;
+                               } else {
+                                       $output['avatar'] = $this->avatars->getGravatar ($hash);
+                               }
                        } else {
                                $output['avatar'] = end ($key_parts);
```


### License
![WTFPL Logo](//upload.wikimedia.org/wikipedia/commons/thumb/0/05/WTFPL_logo.svg/140px-WTFPL_logo.svg.png)

Copyright 2018 Jakub Jankiewicz <https://jcubic.pl/>

Released Under WTFPL License
