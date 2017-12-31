#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <unistd.h>

#define BUF_SIZE 200

int main(int argc, char *argv[])
{
	int sock_fd, conn_fd;
	struct sockaddr_in server_addr;
	char buff[BUF_SIZE];
	int ret;
	int port_number = 2047;

	// 创建socket描述符
	if ((sock_fd = socket(AF_INET, SOCK_STREAM, 0)) == -1) {
		fprintf(stderr,"Socket error:%s\n\a", strerror(errno));
		exit(1);
	}

	// 填充sockaddr_in结构
	bzero(&server_addr, sizeof(struct sockaddr_in));
	server_addr.sin_family = AF_INET;
	server_addr.sin_addr.s_addr = htonl(INADDR_ANY);
	server_addr.sin_port = htons(port_number);

	// 绑定sock_fd描述符
	if (bind(sock_fd, (struct sockaddr *)(&server_addr), sizeof(struct sockaddr)) == -1) {
		fprintf(stderr,"Bind error:%s\n\a", strerror(errno));
		exit(1);
	}

	// 监听sock_fd描述符
	if(listen(sock_fd, 5) == -1) {
		fprintf(stderr,"Listen error:%s\n\a", strerror(errno));
		exit(1);
	}

while(1) {
    // 接受请求
    if ((conn_fd = accept(sock_fd, (struct sockaddr *)NULL, NULL)) == -1) {
        printf("accept socket error: %s\n\a", strerror(errno));
        continue;
    }

    // 开子进程处理数据
    if (fork() == 0) {
        while(1) {
            // 接受数据
            ret = recv(conn_fd, buff, BUF_SIZE, 0);
            if (ret <= 0) {
                // 客户端关闭
                printf("client close\n");
                close(conn_fd);
                break;
            } else {

                // 添加结束符
                if (ret < BUF_SIZE) {
                    memset(&buff[ret], '\0', 1);
                }
                printf("recv msg from client: %s\n", buff);

                // 发送数据
                send(conn_fd, "Hello", 6, 0);
            }
        }
        close(conn_fd);
        exit(0);
    }
}

close(sock_fd);
exit(0);
}