#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <unistd.h>
#include <arpa/inet.h>
#include <sys/types.h>
#include <netdb.h>

#define BUF_SIZE 200

int main(int argc, char *argv[])
{
	int sock_fd, conn_fd;
	struct sockaddr_in server_addr, client_addr;
	char buff[BUF_SIZE];
	int ret;
	int port_number = 2047;

	// 创建socket描述符
	if ((sock_fd = socket(AF_INET, SOCK_STREAM, 0)) == -1)
	{
		fprintf(stderr,"Socket error:%s\n\a", strerror(errno));
		exit(1);
	}

	// 填充sockaddr_in结构
	bzero(&server_addr, sizeof(struct sockaddr_in));
	server_addr.sin_family = AF_INET;
	server_addr.sin_addr.s_addr = htonl(INADDR_ANY);
	server_addr.sin_port = htons(port_number);

	// 绑定sock_fd描述符
	if (bind(sock_fd, (struct sockaddr *)(&server_addr), sizeof(struct sockaddr)) == -1)
	{
		fprintf(stderr,"Bind error:%s\n\a", strerror(errno));
		exit(1);
	}

	// 监听sock_fd描述符
	if(listen(sock_fd, 5) == -1)
	{
		fprintf(stderr,"Listen error:%s\n\a", strerror(errno));
		exit(1);
	}


	int i;
	int conn_num = 5; // 最大连接数
	int conn_amount = 0; // 当前连接数
	int max_sock = sock_fd; // 最大文件描述符
	struct timeval tv; // 超时时间
	tv.tv_sec = 30;
	tv.tv_usec = 0;
	fd_set fdsr; // 文件描述符集
	int fd_A[conn_num]; // 客户端连接记录数组
	for (i = 0; i < conn_num; i++) { // 初始化文件集合
		fd_A[i] = 0;
	}

    while(1) {

        // 初始化文件描述符集
        FD_ZERO(&fdsr);

        // 将socket描述符添加到文件描述符集
        FD_SET(sock_fd, &fdsr);

        // 将活动的连接添加到文件描述符集
        for (i = 0; i < conn_num; i++) {
            if (fd_A[i] != 0) {
                FD_SET(fd_A[i], &fdsr);
            }
        }

        // 获取文件描述符集中活跃的连接，没有将堵塞直到超时
        ret = select(max_sock + 1, &fdsr, NULL, NULL, &tv);
        if (ret < 0) {
            perror("select error\n");
            break;
        } else if (ret == 0) {
            printf("timeout\n");
            continue;
        }

        // 检测连接集合里面每个连接是否活跃状态
        for (i = 0; i < conn_amount; i++) {
            if (FD_ISSET(fd_A[i], &fdsr)) {

                // 接受数据
                ret = recv(fd_A[i], buff, BUF_SIZE, 0);
                if (ret <= 0) {
                    // 客户端关闭
                    printf("client[%d] close\n", i);
                    close(fd_A[i]);
                    FD_CLR(fd_A[i], &fdsr);
                    fd_A[i] = 0;
                } else {

                    // 添加结束符
                    if (ret < BUF_SIZE) {
                        memset(&buff[ret], '\0', 1);
                    }
                    printf("client[%d] send:%s\n", i, buff);

                    // 发送数据
                    send(fd_A[i], "Hello", 6, 0);
                }
            }
        }

        // 新的连接
        if (FD_ISSET(sock_fd, &fdsr)) {
            conn_fd = accept(sock_fd, (struct sockaddr *)NULL, NULL);
            if (conn_fd <= 0) {
                perror("accept error\n");
                continue;
            }

            // 将新连接添加到文件描述符集
            if (conn_amount < conn_num) {
                fd_A[conn_amount++] = conn_fd;
                printf("new connection client[%d] %s:%d\n", conn_amount,
                       inet_ntoa(client_addr.sin_addr), ntohs(client_addr.sin_port));
                if (conn_fd > max_sock)
                    max_sock = conn_fd;
            }
            else {
                printf("max connections arrive, exit\n");
                send(conn_fd, "bye", 4, 0);
                close(conn_fd);
                break;
            }
        }

        // 查看当前客户端状态
        printf("client amount: %d\n", conn_amount);
        for (i = 0; i < conn_num; i++) {
            printf("[%d]:%d  ", i, fd_A[i]);
        }
        printf("\n\n");
    }

    // 关闭连接
    for (i = 0; i < conn_num; i++) {
        if (fd_A[i] != 0) {
            close(fd_A[i]);
        }
    }
    exit(0);
}